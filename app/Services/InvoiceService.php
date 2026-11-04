<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invoices\InvoiceIssueData;
use App\Data\Invoices\InvoiceListItemData;
use App\Data\Invoices\InvoicePaymentData;
use App\Data\Invoices\InvoiceStatsData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\CurrencyEnum;
use App\Enums\EfaStatusEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentSourceEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\InvoiceIssued;
use App\Scopes\TenantScope;
use App\Support\CustomerParty;
use App\Utils\AllowedFilter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class InvoiceService
{
    private const COPY_EXCEPT = [
        'number', 'variable_symbol', 'credited_invoice_id', 'recurring_invoice_id', 'quote_id',
        'period_from', 'period_to', 'issued_at', 'issued_by_name', 'sent_at', 'paid_at', 'cancelled_at',
        'supplier_signature_media_id', 'efa_id', 'efa_submitted_at', 'efa_last_error', 'deleted_at',
    ];

    public function __construct(
        private InvoiceNumberService $numberService,
        private DatabaseManager $db,
        private DocumentItemService $items,
    ) {}

    /**
     * @return LengthAwarePaginator<int, InvoiceListItemData>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Invoice::query())
            ->allowedFilters(
                AllowedFilter::search(['number', 'customer_name']),
                AllowedFilter::dynamic('number'),
                AllowedFilter::dynamic('status'),
                AllowedFilter::dynamic('type'),
                AllowedFilter::dynamic('client_id')->uuid(),
                AllowedFilter::dynamic('customer_name'),
                AllowedFilter::dynamic('issue_date')->date(),
                AllowedFilter::dynamic('due_date')->date(),
                AllowedFilter::dynamic('total')->numeric(),
                AllowedFilter::dynamic('created_at')->date(),
            )
            ->allowedSorts('number', 'customer_name', 'status', 'type', 'issue_date', 'due_date', 'total', 'created_at')
            ->defaultSort('-created_at')
            ->with('client:id,name')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (Invoice $invoice) => InvoiceListItemData::fromModel($invoice));
    }

    public function stats(): InvoiceStatsData
    {
        $base = Invoice::query();

        $issuedMonth = (clone $base)
            ->whereIn('status', [InvoiceStatusEnum::Issued, InvoiceStatusEnum::Overdue, InvoiceStatusEnum::Paid])
            ->whereYear('issue_date', now()->year)
            ->whereMonth('issue_date', now()->month);

        $overdue = (clone $base)->where('status', InvoiceStatusEnum::Overdue);

        $pending = (clone $base)->where('status', InvoiceStatusEnum::Issued);

        $recurring = (clone $base)
            ->where('type', InvoiceTypeEnum::Monthly)
            ->whereIn('status', [InvoiceStatusEnum::Issued, InvoiceStatusEnum::Paid]);

        $openBalance = DB::raw('total - deposit - paid_amount');

        $tenant = Tenant::withoutGlobalScopes()->with('interface')->find(current_tenant_id());
        $currency = $tenant?->interface->default_currency ?? CurrencyEnum::EUR;

        return InvoiceStatsData::fromAggregates(
            (float) $issuedMonth->sum('total'), $issuedMonth->count(),
            (float) $overdue->sum($openBalance), $overdue->count(),
            (float) $pending->sum($openBalance), $pending->count(),
            (float) $recurring->sum('total'), $recurring->count(),
            $currency,
        );
    }

    public function create(InvoiceUpsertData $data): Invoice
    {
        return $this->db->transaction(function () use ($data): Invoice {
            $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

            $client = $data->client_id !== null ? Client::findOrFail($data->client_id) : null;
            $object = $data->cleaning_object_id !== null ? CleaningObject::findOrFail($data->cleaning_object_id) : null;
            $attributes = $this->buildAttributes($data, $tenant, $client, $object);
            $invoice = Invoice::create($attributes);

            $this->items->replacePriced($invoice->items(), $data->items, (bool) $tenant->is_vat_payer);

            $this->recalculateTotals($invoice);

            return $invoice->load('items');
        });
    }

    public function update(Invoice $invoice, InvoiceUpsertData $data): Invoice
    {
        if (! $invoice->isEditable()) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_not_editable')]]);
        }

        return $this->db->transaction(function () use ($invoice, $data): Invoice {
            $tenant = Tenant::withoutGlobalScopes()->with('interface')->findOrFail($invoice->tenant_id);

            $client = $data->client_id !== null ? Client::findOrFail($data->client_id) : null;
            $object = $data->cleaning_object_id !== null ? CleaningObject::findOrFail($data->cleaning_object_id) : null;

            $attributes = $this->buildAttributes($data, $tenant, $client, $object);

            $invoice->update($attributes);

            $this->items->replacePriced($invoice->items(), $data->items, (bool) $invoice->is_vat_payer);

            $this->recalculateTotals($invoice);

            return $invoice->load('items');
        });
    }

    public function issue(Invoice $invoice, InvoiceIssueData $data, ?User $actor): Invoice
    {
        if ($invoice->status !== InvoiceStatusEnum::Draft) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_not_draft')]]);
        }

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($invoice->tenant_id);

        if ($tenant->missingSupplierFields() !== []) {
            throw ValidationException::withMessages(['supplier' => [__('app.invoice_supplier_incomplete')]]);
        }

        return $this->db->transaction(function () use ($invoice, $data, $tenant, $actor): Invoice {
            if ($data->number !== null) {
                $taken = Invoice::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $invoice->tenant_id)
                    ->where('number', $data->number)
                    ->where('id', '!=', $invoice->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($taken) {
                    throw ValidationException::withMessages(['number' => [__('app.invoice_number_taken')]]);
                }

                $number = $data->number;
            } else {
                $number = $this->numberService->next($tenant, $invoice->issue_date);
            }

            $invoice->update([
                'number' => $number,
                'variable_symbol' => $this->numberService->variableSymbol($number),
                'status' => InvoiceStatusEnum::Issued,
                'issued_at' => now(),
                'issued_by_name' => $this->resolveIssuerName($invoice->tenant_id, $actor),
                'supplier_signature_media_id' => $tenant->signature_media_id,
            ]);

            return $invoice;
        });
    }

    public function recordPayment(Invoice $invoice, InvoicePaymentData $data, ?User $actor): InvoicePayment
    {
        if (! $invoice->status->canTransitionTo(InvoiceStatusEnum::Paid)) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_cannot_mark_paid')]]);
        }

        return $this->db->transaction(function () use ($invoice, $data, $actor): InvoicePayment {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $locked->status->canTransitionTo(InvoiceStatusEnum::Paid)) {
                throw ValidationException::withMessages(['status' => [__('app.invoice_cannot_mark_paid')]]);
            }

            if ($data->amount <= 0) {
                throw ValidationException::withMessages(['amount' => [__('app.invoice_payment_amount_positive')]]);
            }

            $balance = $locked->balance_due;

            if (round($data->amount - abs($balance), 2) > 0) {
                throw ValidationException::withMessages(['amount' => [__('app.invoice_payment_exceeds_balance')]]);
            }

            $payment = InvoicePayment::create([
                'tenant_id' => $locked->tenant_id,
                'invoice_id' => $locked->id,
                'amount' => $balance < 0 ? -$data->amount : $data->amount,
                'paid_on' => $data->paid_on,
                'payment_type' => $data->payment_type,
                'source' => PaymentSourceEnum::Manual,
                'note' => $data->note,
                'recorded_by_user_id' => $actor?->id,
            ]);

            $this->recomputePaymentState($locked, Carbon::parse($data->paid_on)->startOfDay());
            $invoice->refresh();

            return $payment;
        });
    }

    public function deletePayment(Invoice $invoice, InvoicePayment $payment): void
    {
        if ($invoice->status === InvoiceStatusEnum::Cancelled) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_payment_locked')]]);
        }

        $this->db->transaction(function () use ($invoice, $payment): void {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $payment->delete();

            $this->recomputePaymentState($locked, null);
            $invoice->refresh();
        });
    }

    private function recomputePaymentState(Invoice $invoice, ?Carbon $settledAt): void
    {
        $paid = round((float) InvoicePayment::query()->where('invoice_id', $invoice->id)->sum('amount'), 2);

        $invoice->paid_amount = number_format($paid, 2, '.', '');

        if ($invoice->isSettled() && $invoice->status !== InvoiceStatusEnum::Paid && $invoice->status->canTransitionTo(InvoiceStatusEnum::Paid)) {
            $invoice->status = InvoiceStatusEnum::Paid;
            $invoice->paid_at = $settledAt ?? now();
        } elseif (! $invoice->isSettled() && $invoice->status === InvoiceStatusEnum::Paid) {
            $invoice->status = $invoice->due_date->isBefore(today()) ? InvoiceStatusEnum::Overdue : InvoiceStatusEnum::Issued;
            $invoice->paid_at = null;
        }

        $invoice->save();
    }

    public function cancel(Invoice $invoice, ?User $actor): Invoice
    {
        if (! $invoice->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_cannot_cancel')]]);
        }

        return $this->db->transaction(function () use ($invoice, $actor): Invoice {
            $invoice->update(['status' => InvoiceStatusEnum::Cancelled, 'cancelled_at' => now()]);

            $tenant = Tenant::withoutGlobalScopes()->findOrFail($invoice->tenant_id);
            $creditNumber = $this->numberService->next($tenant, now());

            $invoice->loadMissing('items');

            $negatedBreakdown = array_map(
                fn (array $line): array => [
                    'rate' => $line['rate'],
                    'base' => -1 * $line['base'],
                    'vat' => -1 * $line['vat'],
                    'total' => -1 * $line['total'],
                ],
                $invoice->vat_breakdown ?? [],
            );

            $today = now()->toDateString();

            $creditNote = $invoice->replicate(self::COPY_EXCEPT)->withoutRelations()->fill([
                'credited_invoice_id' => $invoice->id,
                'status' => InvoiceStatusEnum::Issued,
                'number' => $creditNumber,
                'variable_symbol' => $this->numberService->variableSymbol($creditNumber),
                'issue_date' => $today,
                'delivery_date' => $today,
                'due_date' => $today,
                'issued_at' => now(),
                'issued_by_name' => $this->resolveIssuerName($invoice->tenant_id, $actor),
                'supplier_signature_media_id' => $tenant->signature_media_id,
                'subtotal' => -1 * (float) $invoice->subtotal,
                'vat_amount' => -1 * (float) $invoice->vat_amount,
                'total' => -1 * (float) $invoice->total,
                'deposit' => -1 * (float) $invoice->deposit,
                'vat_breakdown' => $negatedBreakdown,
                'rounding_amount' => -1 * (float) $invoice->rounding_amount,
                'paid_amount' => '0.00',
                'efa_status' => EfaStatusEnum::None,
            ]);
            $creditNote->save();

            /** @var InvoiceItem $item */
            foreach ($invoice->items as $item) {
                $creditNote->items()->save($item->replicate()->fill([
                    'unit_price' => -1 * (float) $item->unit_price,
                    'line_base' => -1 * (float) $item->line_base,
                    'line_vat' => -1 * (float) $item->line_vat,
                    'line_total' => -1 * (float) $item->line_total,
                ]));
            }

            return $invoice;
        });
    }

    public function duplicate(Invoice $invoice): Invoice
    {
        return $this->db->transaction(function () use ($invoice): Invoice {
            $invoice->loadMissing('items');

            $today = now()->toDateString();
            $defaultDueDays = config('invoicing.default_due_days', 14);
            $dueDateDefault = now()->addDays(is_numeric($defaultDueDays) ? (int) $defaultDueDays : 14)->toDateString();

            $newInvoice = $invoice->replicate(self::COPY_EXCEPT)->withoutRelations()->fill([
                'status' => InvoiceStatusEnum::Draft,
                'issue_date' => $today,
                'delivery_date' => $today,
                'due_date' => $dueDateDefault,
                'paid_amount' => '0.00',
                'efa_status' => EfaStatusEnum::None,
            ]);
            $newInvoice->save();

            foreach ($invoice->items as $item) {
                $newInvoice->items()->save($item->replicate());
            }

            return $newInvoice->load('items');
        });
    }

    public function delete(Invoice $invoice): void
    {
        if (! $invoice->isEditable()) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_not_editable')]]);
        }

        $this->db->transaction(function () use ($invoice): void {
            $invoice->delete();
        });
    }

    public function send(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatusEnum::Issued) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_not_issued_to_send')]]);
        }

        if ($invoice->customer_email === null) {
            throw ValidationException::withMessages(['customer_email' => [__('app.invoice_no_customer_email')]]);
        }

        Notification::route('mail', $invoice->customer_email)->notify(new InvoiceIssued($invoice->id));
    }

    /**
     * Actor's `TenantMembership::display_name` for the given tenant, else the actor's global
     * name, else the translated "Automaticky" marker for the unattended (queue) issuer path.
     * Never sourced from request input — see gate 1b.
     */
    private function resolveIssuerName(string $tenantId, ?User $actor): string
    {
        if ($actor === null) {
            return __('app.invoice_issued_by_system');
        }

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $actor->id)
            ->first();

        return $membership !== null ? $membership->display_name : $actor->name;
    }

    private function recalculateTotals(Invoice $invoice): void
    {
        $invoice->loadMissing('items');

        $invoice->update($this->items->totals($invoice->items, $invoice->is_vat_payer, $invoice->rounding_mode));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributes(
        InvoiceUpsertData $data,
        Tenant $tenant,
        ?Client $client,
        ?CleaningObject $object,
    ): array {
        return [
            'client_id' => $data->client_id,
            'cleaning_object_id' => $data->cleaning_object_id,
            'type' => $data->type,
            'status' => InvoiceStatusEnum::Draft,
            'period_from' => $data->period_from,
            'period_to' => $data->period_to,
            'issue_date' => $data->issue_date,
            'delivery_date' => $data->delivery_date,
            'due_date' => $data->due_date,
            'is_vat_payer' => $tenant->is_vat_payer,
            'vat_rate' => $tenant->is_vat_payer ? $tenant->vat_rate : null,
            'deposit' => $data->deposit,
            'constant_symbol' => $data->constant_symbol,
            'specific_symbol' => $data->specific_symbol,
            'payment_type' => $data->payment_type,
            'currency' => $data->currency,
            'rounding_mode' => $data->rounding_mode,
            'header_text' => $data->header_text,
            'footer_text' => $data->footer_text,
            ...CustomerParty::forInvoice($object, $client, CustomerParty::fromEntered($data))->toInvoiceAttributes(),
            ...$this->supplierAttributes($tenant),
            ...$this->objectAttributes($object),
            'note' => $data->note,
        ];
    }

    /** @return array<string, mixed> */
    private function supplierAttributes(Tenant $tenant): array
    {
        return [
            'supplier_peppol_participant_id' => $tenant->peppol_participant_id,
            'supplier_name' => $tenant->name,
            'supplier_ico' => $tenant->ico,
            'supplier_dic' => $tenant->dic,
            'supplier_vat_number' => $tenant->vat_number,
            'supplier_iban' => $tenant->iban,
            'supplier_swift' => $tenant->swift_bic,
            'supplier_address_line' => $tenant->address_line,
            'supplier_city' => $tenant->city,
            'supplier_postal_code' => $tenant->postal_code,
            'supplier_country' => $tenant->country,
            'supplier_contact_email' => $tenant->contact_email,
            'supplier_contact_phone' => $tenant->contact_phone,
            'supplier_registration_info' => $tenant->registration_info,
        ];
    }

    /** @return array<string, mixed> */
    private function objectAttributes(?CleaningObject $object): array
    {
        return [
            'object_name' => $object?->name,
            'object_street' => $object?->street,
            'object_city' => $object?->city,
            'object_postal_code' => $object?->postal_code,
        ];
    }
}
