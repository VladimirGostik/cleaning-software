<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invoices\InvoiceIndexFilterData;
use App\Data\Invoices\InvoiceIssueData;
use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\DatabaseManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class InvoiceService
{
    public function __construct(
        private InvoiceNumberService $numberService,
        private DatabaseManager $db,
    ) {}

    /**
     * @return LengthAwarePaginator<Invoice>
     */
    public function paginate(InvoiceIndexFilterData $filter): LengthAwarePaginator
    {
        return QueryBuilder::for(Invoice::class)
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('client_id'),
            )
            ->allowedSorts(
                AllowedSort::field('created_at'),
                AllowedSort::field('due_date'),
                AllowedSort::field('issue_date'),
            )
            ->defaultSort('-created_at')
            ->paginate($filter->per_page)
            ->appends(request()->query());
    }

    public function create(InvoiceUpsertData $data): Invoice
    {
        return $this->db->transaction(function () use ($data): Invoice {
            $tenantId = app('current_tenant_id');
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);

            $client = null;
            $object = null;

            if ($data->client_id !== null) {
                $client = Client::findOrFail($data->client_id);
            }

            if ($data->cleaning_object_id !== null) {
                $object = CleaningObject::findOrFail($data->cleaning_object_id);
            }

            $tenant->loadMissing('interface');
            $tenantInterface = $tenant->interface;
            $templateDefault = $tenantInterface !== null ? ($tenantInterface->invoice_template ?? InvoiceTemplateEnum::Classic) : InvoiceTemplateEnum::Classic;

            $attributes = $this->buildAttributes($data, $tenant, $client, $object, $templateDefault);
            $invoice = Invoice::create($attributes);

            $this->syncItems($invoice, $data->items, $tenant->id);

            $totals = $this->computeTotals($invoice, $tenant->is_vat_payer, (float) $tenant->vat_rate);
            $invoice->update($totals);

            return $invoice->load('items');
        });
    }

    public function update(Invoice $invoice, InvoiceUpsertData $data): Invoice
    {
        if (! $invoice->isEditable()) {
            throw ValidationException::withMessages([
                'status' => [__('app.invoices.not_editable')],
            ]);
        }

        return $this->db->transaction(function () use ($invoice, $data): Invoice {
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($invoice->tenant_id);

            $client = null;
            $object = null;

            if ($data->client_id !== null) {
                $client = Client::findOrFail($data->client_id);
            }

            if ($data->cleaning_object_id !== null) {
                $object = CleaningObject::findOrFail($data->cleaning_object_id);
            }

            $templateDefault = $invoice->template;
            $attributes = $this->buildAttributes($data, $tenant, $client, $object, $templateDefault);

            $invoice->update($attributes);

            $this->syncItems($invoice, $data->items, $tenant->id);

            $totals = $this->computeTotals($invoice, $invoice->is_vat_payer, (float) $invoice->vat_rate);
            $invoice->update($totals);

            return $invoice->load('items');
        });
    }

    public function issue(Invoice $invoice, InvoiceIssueData $data): Invoice
    {
        if ($invoice->status !== InvoiceStatusEnum::Draft) {
            throw ValidationException::withMessages([
                'status' => [__('app.invoices.not_draft')],
            ]);
        }

        return $this->db->transaction(function () use ($invoice, $data): Invoice {
            if ($data->number !== null) {
                $exists = Invoice::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $invoice->tenant_id)
                    ->where('number', $data->number)
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $invoice->id)
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'number' => [__('app.invoices.number_taken')],
                    ]);
                }

                $number = $data->number;
            } else {
                $tenant = Tenant::withoutGlobalScopes()->findOrFail($invoice->tenant_id);
                $number = $this->numberService->next($tenant, $invoice->issue_date);
            }

            $variableSymbol = $this->numberService->variableSymbol($number);

            $invoice->update([
                'number' => $number,
                'variable_symbol' => $variableSymbol,
                'status' => InvoiceStatusEnum::Issued,
                'issued_at' => now(),
            ]);

            return $invoice;
        });
    }

    public function markPaid(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canTransitionTo(InvoiceStatusEnum::Paid)) {
            throw ValidationException::withMessages([
                'status' => [__('app.invoices.cannot_mark_paid')],
            ]);
        }

        $invoice->update([
            'status' => InvoiceStatusEnum::Paid,
            'paid_at' => now(),
        ]);

        return $invoice;
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if (! $invoice->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => [__('app.invoices.cannot_cancel')],
            ]);
        }

        return $this->db->transaction(function () use ($invoice): Invoice {
            $invoice->update([
                'status' => InvoiceStatusEnum::Cancelled,
                'cancelled_at' => now(),
            ]);

            // Create credit note (dobropis) per D7
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($invoice->tenant_id);
            $creditNumber = $this->numberService->next($tenant, now());

            $invoice->loadMissing('items');

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
                'customer_name' => $invoice->customer_name,
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
                    'total' => -1 * (float) $item->total,
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
            $dueDateDefault = now()->addDays(14)->toDateString();

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
                'customer_name' => $invoice->customer_name,
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
                    'total' => $item->total,
                    'position' => $item->position,
                ]);
            }

            return $newInvoice->load('items');
        });
    }

    public function delete(Invoice $invoice): void
    {
        if (! $invoice->isEditable()) {
            throw ValidationException::withMessages([
                'status' => [__('app.invoices.not_editable')],
            ]);
        }

        $this->db->transaction(function () use ($invoice): void {
            $invoice->delete();
        });
    }

    /**
     * @param  array<int, InvoiceItemData>  $items
     */
    private function syncItems(Invoice $invoice, array $items, string $tenantId): void
    {
        $invoice->items()->delete();

        foreach ($items as $position => $itemData) {
            $unitPrice = $itemData->unit_price;
            $quantity = $itemData->quantity;
            $total = round($unitPrice * $quantity, 2);

            InvoiceItem::create([
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice->id,
                'description' => $itemData->description,
                'quantity' => $quantity,
                'unit' => $itemData->unit,
                'unit_price' => $unitPrice,
                'total' => $total,
                'position' => $position,
            ]);
        }
    }

    /**
     * @return array<string, float>
     */
    private function computeTotals(Invoice $invoice, bool $isVatPayer, float $vatRate): array
    {
        $invoice->loadMissing('items');
        $subtotal = $invoice->items->sum(fn (InvoiceItem $item) => (float) $item->total);
        $vatAmount = $isVatPayer ? round($subtotal * ($vatRate / 100), 2) : 0.0;
        $total = round($subtotal + $vatAmount, 2);

        return [
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $total,
        ];
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
        $customerName = $client !== null ? $client->name : ($data->customer_name ?? '');

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
            'customer_name' => $customerName,
            'customer_ico' => $client !== null ? $client->ico : $data->customer_ico,
            'customer_dic' => $client !== null ? $client->dic : $data->customer_dic,
            'customer_vat_number' => $client !== null ? $client->vat_number : $data->customer_vat_number,
            'customer_street' => $client !== null ? $client->street : $data->customer_street,
            'customer_city' => $client !== null ? $client->city : $data->customer_city,
            'customer_postal_code' => $client !== null ? $client->postal_code : $data->customer_postal_code,
            'customer_country' => $client !== null ? $client->country : $data->customer_country,
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
