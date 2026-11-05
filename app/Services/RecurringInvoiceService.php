<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Data\RecurringInvoices\RecurringInvoiceListItemData;
use App\Data\RecurringInvoices\RecurringInvoiceUpsertData;
use App\Enums\PermissionEnum;
use App\Enums\RecurringBilledMonthEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Notifications\InvoiceIssueReminder;
use App\Scopes\TenantScope;
use App\Support\CustomerParty;
use App\Utils\AllowedFilter;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class RecurringInvoiceService
{
    public function __construct(
        private InvoiceService $invoices,
        private DocumentItemService $items,
        private DatabaseManager $db,
        private NotificationRecipientResolver $recipients,
    ) {}

    /**
     * @return LengthAwarePaginator<int, RecurringInvoiceListItemData>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(RecurringInvoice::query())
            ->allowedFilters(
                AllowedFilter::search(['name', 'customer_name']),
                AllowedFilter::dynamic('status'),
                AllowedFilter::dynamic('frequency'),
                AllowedFilter::dynamic('client_id')->uuid(),
                AllowedFilter::dynamic('next_run_at')->date(),
                AllowedFilter::dynamic('created_at')->date(),
            )
            ->allowedSorts('name', 'status', 'frequency', 'next_run_at', 'created_at')
            ->defaultSort('-created_at')
            ->with(['client:id,name', 'cleaningObject:id,name,client_id,billing_name', 'cleaningObject.client:id,name'])
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (RecurringInvoice $ri) => RecurringInvoiceListItemData::fromModel($ri));
    }

    public function create(RecurringInvoiceUpsertData $data): RecurringInvoice
    {
        return $this->db->transaction(function () use ($data): RecurringInvoice {
            $nextRunAt = $this->computeInitialNextRunAt($data);

            $ri = RecurringInvoice::create([
                'tenant_id' => current_tenant_id(),
                'client_id' => $data->client_id,
                'cleaning_object_id' => $data->cleaning_object_id,
                'name' => $data->name,
                'type' => $data->type,
                'frequency' => $data->frequency,
                'billed_month' => $data->billed_month,
                'day_of_month' => $data->day_of_month,
                'status' => RecurringInvoiceStatusEnum::Active,
                'auto_issue' => $data->auto_issue,
                'auto_send' => $data->auto_send,
                'start_date' => $data->start_date,
                'end_date' => $data->end_date,
                'occurrences_limit' => $data->occurrences_limit,
                'occurrences_generated' => 0,
                'next_run_at' => $nextRunAt,
                ...CustomerParty::fromEntered($data)->toEnteredAttributes(),
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

            $this->items->replace($ri->items(), $data->items);

            return $ri->load('items');
        });
    }

    public function update(RecurringInvoice $ri, RecurringInvoiceUpsertData $data): RecurringInvoice
    {
        if (! in_array($ri->status, [RecurringInvoiceStatusEnum::Active, RecurringInvoiceStatusEnum::Paused], true)) {
            throw ValidationException::withMessages(['status' => [__('app.recurring_invoice_not_editable')]]);
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
                'frequency' => $data->frequency,
                'billed_month' => $data->billed_month,
                'day_of_month' => $data->day_of_month,
                'auto_issue' => $data->auto_issue,
                'auto_send' => $data->auto_send,
                'start_date' => $data->start_date,
                'end_date' => $data->end_date,
                'occurrences_limit' => $data->occurrences_limit,
                'next_run_at' => $nextRunAt,
                ...CustomerParty::fromEntered($data)->toEnteredAttributes(),
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

            $this->items->replace($ri->items(), $data->items);

            return $ri->load('items');
        });
    }

    public function pause(RecurringInvoice $ri): RecurringInvoice
    {
        if ($ri->status !== RecurringInvoiceStatusEnum::Active) {
            throw ValidationException::withMessages(['status' => [__('app.recurring_invoice_cannot_pause')]]);
        }

        $ri->update(['status' => RecurringInvoiceStatusEnum::Paused, 'next_run_at' => null]);

        return $ri;
    }

    public function resume(RecurringInvoice $ri): RecurringInvoice
    {
        if ($ri->status !== RecurringInvoiceStatusEnum::Paused) {
            throw ValidationException::withMessages(['status' => [__('app.recurring_invoice_cannot_resume')]]);
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

        $ri->update(['status' => $candidateStatus, 'next_run_at' => $nextRunAt]);

        return $ri;
    }

    public function cancel(RecurringInvoice $ri): RecurringInvoice
    {
        if (! in_array($ri->status, [RecurringInvoiceStatusEnum::Active, RecurringInvoiceStatusEnum::Paused], true)) {
            throw ValidationException::withMessages(['status' => [__('app.recurring_invoice_cannot_cancel')]]);
        }

        $ri->update(['status' => RecurringInvoiceStatusEnum::Cancelled, 'next_run_at' => null]);

        return $ri;
    }

    public function delete(RecurringInvoice $ri): void
    {
        $ri->delete();
    }

    public function generateInvoiceFromTemplate(RecurringInvoice $ri, ?CarbonImmutable $billedMonth = null): ?Invoice
    {
        $ri->loadMissing('items');

        $billedMonth ??= $ri->billed_month->billedMonth($ri->next_run_at ?? now());

        if ($billedMonth !== null && $this->periodAlreadyGenerated($ri, $billedMonth)) {
            return null;
        }

        $today = now()->toDateString();
        $dueDate = now()->addDays($ri->due_days)->toDateString();
        $periodFrom = $billedMonth?->startOfMonth()->toDateString();
        $periodTo = $billedMonth?->endOfMonth()->toDateString();
        $label = $billedMonth === null ? null : RecurringBilledMonthEnum::formatMonth($billedMonth);

        $items = [];
        /** @var RecurringInvoiceItem $item */
        foreach ($ri->items as $item) {
            $items[] = InvoiceItemData::from([
                'id' => null,
                'description' => $this->replaceToken($item->description, $label) ?? $item->description,
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
            'issue_date' => $today,
            'delivery_date' => $periodTo ?? $today,
            'due_date' => $dueDate,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            ...CustomerParty::fromEntered($ri)->toEnteredAttributes(),
            'note' => $this->replaceToken($ri->note, $label),
            'deposit' => (float) $ri->deposit,
            'constant_symbol' => $ri->constant_symbol,
            'specific_symbol' => null,
            'payment_type' => $ri->payment_type->value,
            'currency' => $ri->currency->value,
            'rounding_mode' => $ri->rounding_mode->value,
            'header_text' => $this->replaceToken($ri->header_text, $label),
            'footer_text' => $this->replaceToken($ri->footer_text, $label),
            'items' => $items,
        ]);

        $invoice = $this->invoices->create($upsertData);
        $invoice->update(['recurring_invoice_id' => $ri->id]);

        return $invoice;
    }

    private function periodAlreadyGenerated(RecurringInvoice $ri, CarbonImmutable $billedMonth): bool
    {
        return Invoice::withTrashed()
            ->where('recurring_invoice_id', $ri->id)
            ->whereDate('period_from', $billedMonth->startOfMonth()->toDateString())
            ->exists();
    }

    private function replaceToken(?string $text, ?string $label): ?string
    {
        return $text === null || $label === null ? $text : str_replace(RecurringBilledMonthEnum::TOKEN, $label, $text);
    }

    public function sendIssueReminders(CarbonImmutable $today): int
    {
        $day = min(config()->integer('invoicing.issue_reminder.day'), $today->daysInMonth);

        if ($today->day !== $day) {
            return 0;
        }

        $month = RecurringBilledMonthEnum::formatMonth($today);
        $sent = 0;

        $tenants = Tenant::withoutGlobalScopes()->whereIn('id', RecurringInvoice::withoutGlobalScope(TenantScope::class)
            ->where('status', RecurringInvoiceStatusEnum::Active->value)
            ->where('billed_month', '!=', RecurringBilledMonthEnum::None->value)
            ->select('tenant_id'))->get();

        foreach ($tenants as $tenant) {
            $users = $this->recipients->usersWithPermission($tenant->id, PermissionEnum::EditInvoices);

            if ($users->isEmpty()) {
                continue;
            }

            Notification::send($users, new InvoiceIssueReminder($tenant->id, $month, $tenant->name));
            $sent++;
        }

        return $sent;
    }

    public function resolveTenantDefaultState(string $tenantId): RecurringDefaultStateEnum
    {
        /** @var TenantInterface|null $interface */
        $interface = TenantInterface::query()->where('tenant_id', $tenantId)->first();

        return $interface->recurring_default_state ?? RecurringDefaultStateEnum::Draft;
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
}
