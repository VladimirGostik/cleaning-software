<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invoices\InvoiceIssueData;
use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceListItemData;
use App\Data\Invoices\InvoiceStatsData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Notifications\InvoiceIssued;
use App\Scopes\TenantScope;
use App\Utils\AllowedFilter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class InvoiceService
{
    public function __construct(
        private InvoiceNumberService $numberService,
        private DatabaseManager $db,
        private DocumentTotalsCalculator $totals,
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

        $tenant = Tenant::withoutGlobalScopes()->with('interface')->find(current_tenant_id());
        $currency = $tenant?->interface->default_currency ?? CurrencyEnum::EUR;

        return InvoiceStatsData::fromAggregates(
            (float) $issuedMonth->sum('total'), $issuedMonth->count(),
            (float) $overdue->sum('total'), $overdue->count(),
            (float) $pending->sum('total'), $pending->count(),
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

            $templateDefault = $tenant->interface->invoice_template ?? InvoiceTemplateEnum::Classic;

            $attributes = $this->buildAttributes($data, $tenant, $client, $object, $templateDefault);
            $invoice = Invoice::create($attributes);

            $this->syncItems($invoice, $data->items, $tenant->id, (bool) $tenant->is_vat_payer);

            $invoice->update($this->computeTotals($invoice));

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

            $attributes = $this->buildAttributes($data, $tenant, $client, $object, $invoice->template);

            $invoice->update($attributes);

            $this->syncItems($invoice, $data->items, $tenant->id, (bool) $invoice->is_vat_payer);

            $invoice->update($this->computeTotals($invoice));

            return $invoice->load('items');
        });
    }

    public function issue(Invoice $invoice, InvoiceIssueData $data): Invoice
    {
        if ($invoice->status !== InvoiceStatusEnum::Draft) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_not_draft')]]);
        }

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($invoice->tenant_id);

        if ($tenant->missingSupplierFields() !== []) {
            throw ValidationException::withMessages(['supplier' => [__('app.invoice_supplier_incomplete')]]);
        }

        return $this->db->transaction(function () use ($invoice, $data, $tenant): Invoice {
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
            ]);

            return $invoice;
        });
    }

    public function markPaid(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canTransitionTo(InvoiceStatusEnum::Paid)) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_cannot_mark_paid')]]);
        }

        $invoice->update(['status' => InvoiceStatusEnum::Paid, 'paid_at' => now()]);

        return $invoice;
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if (! $invoice->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_cannot_cancel')]]);
        }

        return $this->db->transaction(function () use ($invoice): Invoice {
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

            $creditNote = Invoice::create([
                'tenant_id' => $invoice->tenant_id,
                'client_id' => $invoice->client_id,
                'cleaning_object_id' => $invoice->cleaning_object_id,
                'credited_invoice_id' => $invoice->id,
                'type' => $invoice->type,
                'status' => InvoiceStatusEnum::Issued,
                'template' => $invoice->template,
                'number' => $creditNumber,
                'variable_symbol' => $this->numberService->variableSymbol($creditNumber),
                'issue_date' => now()->toDateString(),
                'delivery_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'issued_at' => now(),
                'is_vat_payer' => $invoice->is_vat_payer,
                'vat_rate' => $invoice->vat_rate,
                'subtotal' => -1 * (float) $invoice->subtotal,
                'vat_amount' => -1 * (float) $invoice->vat_amount,
                'total' => -1 * (float) $invoice->total,
                'deposit' => -1 * (float) $invoice->deposit,
                'vat_breakdown' => $negatedBreakdown,
                'rounding_amount' => -1 * (float) $invoice->rounding_amount,
                'constant_symbol' => $invoice->constant_symbol,
                'specific_symbol' => $invoice->specific_symbol,
                'payment_type' => $invoice->payment_type,
                'currency' => $invoice->currency,
                'rounding_mode' => $invoice->rounding_mode,
                'header_text' => $invoice->header_text,
                'footer_text' => $invoice->footer_text,
                'customer_name' => $invoice->customer_name,
                'customer_representative' => $invoice->customer_representative,
                'customer_ico' => $invoice->customer_ico,
                'customer_dic' => $invoice->customer_dic,
                'customer_vat_number' => $invoice->customer_vat_number,
                'customer_street' => $invoice->customer_street,
                'customer_city' => $invoice->customer_city,
                'customer_postal_code' => $invoice->customer_postal_code,
                'customer_country' => $invoice->customer_country,
                'customer_email' => $invoice->customer_email,
                'object_name' => $invoice->object_name,
                'object_street' => $invoice->object_street,
                'object_city' => $invoice->object_city,
                'object_postal_code' => $invoice->object_postal_code,
                'supplier_name' => $invoice->supplier_name,
                'supplier_ico' => $invoice->supplier_ico,
                'supplier_dic' => $invoice->supplier_dic,
                'supplier_vat_number' => $invoice->supplier_vat_number,
                'supplier_iban' => $invoice->supplier_iban,
                'supplier_swift' => $invoice->supplier_swift,
                'supplier_address_line' => $invoice->supplier_address_line,
                'supplier_city' => $invoice->supplier_city,
                'supplier_postal_code' => $invoice->supplier_postal_code,
                'supplier_country' => $invoice->supplier_country,
                'supplier_contact_email' => $invoice->supplier_contact_email,
                'supplier_contact_phone' => $invoice->supplier_contact_phone,
                'supplier_registration_info' => $invoice->supplier_registration_info,
                'note' => $invoice->note,
            ]);

            /** @var InvoiceItem $item */
            foreach ($invoice->items as $item) {
                InvoiceItem::create([
                    'tenant_id' => $invoice->tenant_id,
                    'invoice_id' => $creditNote->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => -1 * (float) $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'vat_rate' => $item->vat_rate,
                    'line_base' => -1 * (float) $item->line_base,
                    'line_vat' => -1 * (float) $item->line_vat,
                    'line_total' => -1 * (float) $item->line_total,
                    'position' => $item->position,
                ]);
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

            $newInvoice = Invoice::create([
                'tenant_id' => $invoice->tenant_id,
                'client_id' => $invoice->client_id,
                'cleaning_object_id' => $invoice->cleaning_object_id,
                'type' => $invoice->type,
                'status' => InvoiceStatusEnum::Draft,
                'template' => $invoice->template,
                'number' => null,
                'variable_symbol' => null,
                'issue_date' => $today,
                'delivery_date' => $today,
                'due_date' => $dueDateDefault,
                'is_vat_payer' => $invoice->is_vat_payer,
                'vat_rate' => $invoice->vat_rate,
                'subtotal' => $invoice->subtotal,
                'vat_amount' => $invoice->vat_amount,
                'total' => $invoice->total,
                'deposit' => $invoice->deposit,
                'vat_breakdown' => $invoice->vat_breakdown,
                'rounding_amount' => $invoice->rounding_amount,
                'constant_symbol' => $invoice->constant_symbol,
                'specific_symbol' => $invoice->specific_symbol,
                'payment_type' => $invoice->payment_type,
                'currency' => $invoice->currency,
                'rounding_mode' => $invoice->rounding_mode,
                'header_text' => $invoice->header_text,
                'footer_text' => $invoice->footer_text,
                'customer_name' => $invoice->customer_name,
                'customer_representative' => $invoice->customer_representative,
                'customer_ico' => $invoice->customer_ico,
                'customer_dic' => $invoice->customer_dic,
                'customer_vat_number' => $invoice->customer_vat_number,
                'customer_street' => $invoice->customer_street,
                'customer_city' => $invoice->customer_city,
                'customer_postal_code' => $invoice->customer_postal_code,
                'customer_country' => $invoice->customer_country,
                'customer_email' => $invoice->customer_email,
                'object_name' => $invoice->object_name,
                'object_street' => $invoice->object_street,
                'object_city' => $invoice->object_city,
                'object_postal_code' => $invoice->object_postal_code,
                'supplier_name' => $invoice->supplier_name,
                'supplier_ico' => $invoice->supplier_ico,
                'supplier_dic' => $invoice->supplier_dic,
                'supplier_vat_number' => $invoice->supplier_vat_number,
                'supplier_iban' => $invoice->supplier_iban,
                'supplier_swift' => $invoice->supplier_swift,
                'supplier_address_line' => $invoice->supplier_address_line,
                'supplier_city' => $invoice->supplier_city,
                'supplier_postal_code' => $invoice->supplier_postal_code,
                'supplier_country' => $invoice->supplier_country,
                'supplier_contact_email' => $invoice->supplier_contact_email,
                'supplier_contact_phone' => $invoice->supplier_contact_phone,
                'supplier_registration_info' => $invoice->supplier_registration_info,
                'note' => $invoice->note,
            ]);

            /** @var InvoiceItem $item */
            foreach ($invoice->items as $item) {
                InvoiceItem::create([
                    'tenant_id' => $invoice->tenant_id,
                    'invoice_id' => $newInvoice->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'vat_rate' => $item->vat_rate,
                    'line_base' => $item->line_base,
                    'line_vat' => $item->line_vat,
                    'line_total' => $item->line_total,
                    'position' => $item->position,
                ]);
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
     * @param  array<InvoiceItemData>  $items
     */
    private function syncItems(Invoice $invoice, array $items, string $tenantId, bool $isVatPayer): void
    {
        $invoice->items()->delete();

        foreach ($items as $position => $itemData) {
            $line = $this->totals->line($itemData->quantity, $itemData->unit_price, $itemData->discount_percent, $itemData->vat_rate, $isVatPayer);

            InvoiceItem::create([
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice->id,
                'description' => $itemData->description,
                'quantity' => $itemData->quantity,
                'unit' => $itemData->unit,
                'unit_price' => $itemData->unit_price,
                'discount_percent' => $itemData->discount_percent,
                'vat_rate' => $itemData->vat_rate,
                'line_base' => $line['line_base'],
                'line_vat' => $line['line_vat'],
                'line_total' => $line['line_total'],
                'position' => $position,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function computeTotals(Invoice $invoice): array
    {
        $invoice->loadMissing('items');

        return $this->totals->totals(
            $invoice->items->map(fn (InvoiceItem $item) => $item->only(['vat_rate', 'line_base', 'line_vat', 'line_total']))->all(),
            $invoice->is_vat_payer,
            $invoice->rounding_mode,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributes(
        InvoiceUpsertData $data,
        Tenant $tenant,
        ?Client $client,
        ?CleaningObject $object,
        InvoiceTemplateEnum $templateDefault,
    ): array {
        $customerName = $client->name ?? ($data->customer_name ?? '');
        $customerCountry = $client !== null ? $client->country : ($data->customer_country ?? 'SK');

        return [
            'client_id' => $data->client_id,
            'cleaning_object_id' => $data->cleaning_object_id,
            'type' => $data->type,
            'status' => InvoiceStatusEnum::Draft,
            'template' => $data->template ?? $templateDefault,
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
            'customer_name' => $customerName,
            'customer_representative' => $data->customer_representative,
            'customer_ico' => $client->ico ?? $data->customer_ico,
            'customer_dic' => $client->dic ?? $data->customer_dic,
            'customer_vat_number' => $client->vat_number ?? $data->customer_vat_number,
            'customer_street' => $client->street ?? $data->customer_street,
            'customer_city' => $client->city ?? $data->customer_city,
            'customer_postal_code' => $client->postal_code ?? $data->customer_postal_code,
            'customer_country' => $customerCountry,
            'customer_email' => $data->customer_email,
            'object_name' => $object?->name,
            'object_street' => $object?->street,
            'object_city' => $object?->city,
            'object_postal_code' => $object?->postal_code,
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
            'note' => $data->note,
        ];
    }
}
