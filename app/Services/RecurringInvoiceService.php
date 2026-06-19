<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Data\RecurringInvoices\RecurringInvoiceIndexFilterData;
use App\Data\RecurringInvoices\RecurringInvoiceItemData;
use App\Data\RecurringInvoices\RecurringInvoiceUpsertData;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\TenantInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class RecurringInvoiceService
{
    public function __construct(
        private InvoiceService $invoices,
        private DatabaseManager $db,
    ) {}

    /**
     * @return LengthAwarePaginator<RecurringInvoice>
     */
    public function paginate(RecurringInvoiceIndexFilterData $filter): LengthAwarePaginator
    {
        return QueryBuilder::for(RecurringInvoice::class)
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('frequency'),
                AllowedFilter::exact('client_id'),
            )
            ->allowedSorts(
                AllowedSort::field('created_at'),
                AllowedSort::field('next_run_at'),
                AllowedSort::field('name'),
            )
            ->defaultSort('-created_at')
            ->with(['client', 'cleaningObject.client'])
            ->paginate($filter->per_page)
            ->appends(request()->query());
    }

    public function create(RecurringInvoiceUpsertData $data): RecurringInvoice
    {
        return $this->db->transaction(function () use ($data): RecurringInvoice {
            $tenantId = app('current_tenant_id');

            $nextRunAt = $this->computeInitialNextRunAt($data);

            $ri = RecurringInvoice::create([
                'tenant_id' => $tenantId,
                'client_id' => $data->client_id,
                'cleaning_object_id' => $data->cleaning_object_id,
                'name' => $data->name,
                'type' => $data->type,
                'template' => $data->template,
                'frequency' => $data->frequency,
                'day_of_month' => $data->day_of_month,
                'status' => RecurringInvoiceStatusEnum::Active,
                'auto_issue' => $data->auto_issue,
                'start_date' => $data->start_date,
                'end_date' => $data->end_date,
                'occurrences_limit' => $data->occurrences_limit,
                'occurrences_generated' => 0,
                'next_run_at' => $nextRunAt,
                'customer_name' => $data->customer_name,
                'customer_representative' => $data->customer_representative,
                'customer_ico' => $data->customer_ico,
                'customer_dic' => $data->customer_dic,
                'customer_vat_number' => $data->customer_vat_number,
                'customer_street' => $data->customer_street,
                'customer_city' => $data->customer_city,
                'customer_postal_code' => $data->customer_postal_code,
                'customer_country' => $data->customer_country,
                'customer_email' => $data->customer_email,
                'period_from' => $data->period_from,
                'period_to' => $data->period_to,
                'due_days' => $data->due_days,
                'deposit' => $data->deposit,
                'note' => $data->note,
                'constant_symbol' => $data->constant_symbol,
                'payment_type' => $data->payment_type,
                'currency' => $data->currency,
                'rounding_mode' => $data->rounding_mode,
                'header_text' => $data->header_text,
                'footer_text' => $data->footer_text,
            ]);

            $this->syncItems($ri, $data->items);

            return $ri->load('items');
        });
    }

    public function update(RecurringInvoice $ri, RecurringInvoiceUpsertData $data): RecurringInvoice
    {
        if (! in_array($ri->status, [RecurringInvoiceStatusEnum::Active, RecurringInvoiceStatusEnum::Paused], true)) {
            throw ValidationException::withMessages([
                'status' => [__('app.recurring_invoices.not_editable')],
            ]);
        }

        return $this->db->transaction(function () use ($ri, $data): RecurringInvoice {
            $nextRunAt = $ri->status === RecurringInvoiceStatusEnum::Active
                ? $this->computeInitialNextRunAt($data)
                : $ri->next_run_at;

            $ri->update([
                'client_id' => $data->client_id,
                'cleaning_object_id' => $data->cleaning_object_id,
                'name' => $data->name,
                'type' => $data->type,
                'template' => $data->template,
                'frequency' => $data->frequency,
                'day_of_month' => $data->day_of_month,
                'auto_issue' => $data->auto_issue,
                'start_date' => $data->start_date,
                'end_date' => $data->end_date,
                'occurrences_limit' => $data->occurrences_limit,
                'next_run_at' => $nextRunAt,
                'customer_name' => $data->customer_name,
                'customer_representative' => $data->customer_representative,
                'customer_ico' => $data->customer_ico,
                'customer_dic' => $data->customer_dic,
                'customer_vat_number' => $data->customer_vat_number,
                'customer_street' => $data->customer_street,
                'customer_city' => $data->customer_city,
                'customer_postal_code' => $data->customer_postal_code,
                'customer_country' => $data->customer_country,
                'customer_email' => $data->customer_email,
                'period_from' => $data->period_from,
                'period_to' => $data->period_to,
                'due_days' => $data->due_days,
                'deposit' => $data->deposit,
                'note' => $data->note,
                'constant_symbol' => $data->constant_symbol,
                'payment_type' => $data->payment_type,
                'currency' => $data->currency,
                'rounding_mode' => $data->rounding_mode,
                'header_text' => $data->header_text,
                'footer_text' => $data->footer_text,
            ]);

            $this->syncItems($ri, $data->items);

            return $ri->load('items');
        });
    }

    public function pause(RecurringInvoice $ri): RecurringInvoice
    {
        if ($ri->status !== RecurringInvoiceStatusEnum::Active) {
            throw ValidationException::withMessages([
                'status' => [__('app.recurring_invoices.cannot_pause')],
            ]);
        }

        $ri->update([
            'status' => RecurringInvoiceStatusEnum::Paused,
            'next_run_at' => null,
        ]);

        return $ri;
    }

    public function resume(RecurringInvoice $ri): RecurringInvoice
    {
        if ($ri->status !== RecurringInvoiceStatusEnum::Paused) {
            throw ValidationException::withMessages([
                'status' => [__('app.recurring_invoices.cannot_resume')],
            ]);
        }

        $today = now()->startOfDay();
        $base = $today->gt($ri->start_date) ? $today : $ri->start_date->copy()->startOfDay();
        $nextRunAt = $ri->frequency->nextRunDate($base, $ri->day_of_month);

        $candidateStatus = RecurringInvoiceStatusEnum::Active;

        if ($ri->occurrences_limit !== null && $ri->occurrences_generated >= $ri->occurrences_limit) {
            $candidateStatus = RecurringInvoiceStatusEnum::Completed;
            $nextRunAt = null;
        } elseif ($ri->end_date !== null && $nextRunAt->gt($ri->end_date)) {
            $candidateStatus = RecurringInvoiceStatusEnum::Completed;
            $nextRunAt = null;
        }

        $ri->update([
            'status' => $candidateStatus,
            'next_run_at' => $nextRunAt,
        ]);

        return $ri;
    }

    public function cancel(RecurringInvoice $ri): RecurringInvoice
    {
        if (! in_array($ri->status, [RecurringInvoiceStatusEnum::Active, RecurringInvoiceStatusEnum::Paused], true)) {
            throw ValidationException::withMessages([
                'status' => [__('app.recurring_invoices.cannot_cancel')],
            ]);
        }

        $ri->update([
            'status' => RecurringInvoiceStatusEnum::Cancelled,
            'next_run_at' => null,
        ]);

        return $ri;
    }

    public function delete(RecurringInvoice $ri): void
    {
        $ri->delete();
    }

    public function generateInvoiceFromTemplate(RecurringInvoice $ri): Invoice
    {
        $ri->loadMissing('items');

        $today = now()->toDateString();
        $dueDate = now()->addDays($ri->due_days)->toDateString();

        $items = [];
        /** @var RecurringInvoiceItem $item */
        foreach ($ri->items as $item) {
            $items[] = InvoiceItemData::from([
                'id' => null,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float) $item->unit_price,
                'discount_percent' => (float) $item->discount_percent,
                'vat_rate' => (float) $item->vat_rate,
                'line_base' => null,
                'line_vat' => null,
                'line_total' => null,
            ]);
        }

        $upsertData = InvoiceUpsertData::from([
            'client_id' => $ri->client_id,
            'cleaning_object_id' => $ri->cleaning_object_id,
            'type' => $ri->type->value,
            'template' => $ri->template?->value,
            'issue_date' => $today,
            'delivery_date' => $today,
            'due_date' => $dueDate,
            'period_from' => $ri->period_from?->toDateString(),
            'period_to' => $ri->period_to?->toDateString(),
            'customer_name' => $ri->customer_name,
            'customer_representative' => $ri->customer_representative,
            'customer_ico' => $ri->customer_ico,
            'customer_dic' => $ri->customer_dic,
            'customer_vat_number' => $ri->customer_vat_number,
            'customer_street' => $ri->customer_street,
            'customer_city' => $ri->customer_city,
            'customer_postal_code' => $ri->customer_postal_code,
            'customer_country' => $ri->customer_country,
            'customer_email' => $ri->customer_email,
            'note' => $ri->note,
            'deposit' => (float) $ri->deposit,
            'constant_symbol' => $ri->constant_symbol,
            'specific_symbol' => null,
            'payment_type' => $ri->payment_type->value,
            'currency' => $ri->currency->value,
            'rounding_mode' => $ri->rounding_mode->value,
            'header_text' => $ri->header_text,
            'footer_text' => $ri->footer_text,
            'items' => $items,
        ]);

        $invoice = $this->invoices->create($upsertData);

        $invoice->recurring_invoice_id = $ri->id;
        $invoice->save();

        return $invoice;
    }

    public function resolveTenantDefaultState(string $tenantId): RecurringDefaultStateEnum
    {
        /** @var TenantInterface|null $interface */
        $interface = TenantInterface::query()->where('tenant_id', $tenantId)->first();

        if ($interface === null) {
            return RecurringDefaultStateEnum::Draft;
        }

        return $interface->recurring_default_state;
    }

    private function computeInitialNextRunAt(RecurringInvoiceUpsertData $data): Carbon
    {
        $startDate = Carbon::parse($data->start_date)->startOfDay();
        $today = now()->startOfDay();

        if ($startDate->gt($today)) {
            $maxDay = min($data->day_of_month, $startDate->daysInMonth);
            $startDate->setDay($maxDay);

            return $startDate;
        }

        $base = $today->copy();
        $maxDay = min($data->day_of_month, $base->daysInMonth);

        if ($maxDay >= $today->day) {
            return $base->setDay($maxDay);
        }

        return $data->frequency->nextRunDate($today, $data->day_of_month);
    }

    /**
     * @param  RecurringInvoiceItemData[]  $items
     */
    private function syncItems(RecurringInvoice $ri, array $items): void
    {
        $ri->items()->delete();

        foreach ($items as $position => $itemData) {
            RecurringInvoiceItem::create([
                'tenant_id' => $ri->tenant_id,
                'recurring_invoice_id' => $ri->id,
                'description' => $itemData->description,
                'quantity' => $itemData->quantity,
                'unit' => $itemData->unit,
                'unit_price' => $itemData->unit_price,
                'discount_percent' => $itemData->discount_percent,
                'vat_rate' => $itemData->vat_rate,
                'position' => $position,
            ]);
        }
    }
}
