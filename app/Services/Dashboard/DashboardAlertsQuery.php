<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Data\Dashboard\DashboardAlertData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\DashboardAlertTypeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\JobStatusEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

/**
 * Merged, capped, urgency-sorted cross-tenant alert feed. Every list query removes only
 * `TenantScope` (never `withoutGlobalScopes()`), including on eager-loaded relations that
 * themselves carry `BelongsToTenant` — otherwise the relation query re-applies the currently
 * bound tenant and silently drops rows belonging to any other tenant in the feed.
 */
final readonly class DashboardAlertsQuery
{
    /**
     * @param  list<TenantVisibility>  $visibilities
     * @return array{rows: list<DashboardAlertData>, counts: array<string, int>}
     */
    public function collect(array $visibilities): array
    {
        /** @var array<string, Tenant> $tenants */
        $tenants = [];
        foreach ($visibilities as $visibility) {
            $tenants[$visibility->tenant->id] = $visibility->tenant;
        }

        $perTypeLimit = $this->intConfig('dashboard.alerts.per_type_limit', 15);
        $totalLimit = $this->intConfig('dashboard.alerts.total_limit', 50);
        $today = Carbon::today();

        $counts = array_fill_keys(
            array_map(fn (DashboardAlertTypeEnum $type): string => $type->value, DashboardAlertTypeEnum::cases()),
            0,
        );

        /** @var list<DashboardAlertData> $rows */
        $rows = [];

        [$overdueRows, $overdueCount] = $this->overdueInvoices($visibilities, $tenants, $perTypeLimit);
        $rows = [...$rows, ...$overdueRows];
        $counts[DashboardAlertTypeEnum::OverdueInvoice->value] = $overdueCount;

        $supplierRows = $this->supplierIncomplete($visibilities);
        $rows = [...$rows, ...$supplierRows];
        $counts[DashboardAlertTypeEnum::SupplierIncomplete->value] = count($supplierRows);

        [$unassignedRows, $unassignedCount] = $this->unassignedJobs($visibilities, $tenants, $today, $perTypeLimit);
        $rows = [...$rows, ...$unassignedRows];
        $counts[DashboardAlertTypeEnum::UnassignedJob->value] = $unassignedCount;

        [$contractRows, $contractCount] = $this->expiringContracts($visibilities, $tenants, $today, $perTypeLimit);
        $rows = [...$rows, ...$contractRows];
        $counts[DashboardAlertTypeEnum::ContractExpiring->value] = $contractCount;

        [$quoteRows, $quoteCount] = $this->expiringQuotes($visibilities, $tenants, $today, $perTypeLimit);
        $rows = [...$rows, ...$quoteRows];
        $counts[DashboardAlertTypeEnum::QuoteExpiring->value] = $quoteCount;

        usort($rows, fn (DashboardAlertData $a, DashboardAlertData $b): int => [$a->type->rank(), $this->urgency($a)] <=> [$b->type->rank(), $this->urgency($b)]);

        return [
            'rows' => array_slice($rows, 0, $totalLimit),
            'counts' => $counts,
        ];
    }

    private function urgency(DashboardAlertData $alert): int
    {
        return match ($alert->type) {
            DashboardAlertTypeEnum::OverdueInvoice => -($alert->days ?? 0),
            DashboardAlertTypeEnum::SupplierIncomplete => 0,
            default => $alert->days ?? 0,
        };
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @param  array<string, Tenant>  $tenants
     * @return array{0: list<DashboardAlertData>, 1: int}
     */
    private function overdueInvoices(array $visibilities, array $tenants, int $limit): array
    {
        $ids = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->invoices);

        if ($ids === []) {
            return [[], 0];
        }

        $base = Invoice::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $ids)
            ->where('status', InvoiceStatusEnum::Overdue->value)
            ->whereNull('credited_invoice_id');

        $count = (clone $base)->count();

        $rows = array_values((clone $base)
            ->orderBy('due_date')
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $invoice) => DashboardAlertData::fromInvoice($invoice, $tenants[$invoice->tenant_id]))
            ->all());

        return [$rows, $count];
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @return list<DashboardAlertData>
     */
    private function supplierIncomplete(array $visibilities): array
    {
        $rows = [];

        foreach ($visibilities as $visibility) {
            if ($visibility->tenant->hasCompleteSupplierProfile()) {
                continue;
            }

            if (! $visibility->billingSettings && ! $visibility->createInvoices) {
                continue;
            }

            $url = $visibility->billingSettings ? route('settings.invoicing', absolute: false) : route('invoices.index', absolute: false);

            $rows[] = DashboardAlertData::supplierIncomplete($visibility->tenant, $url);
        }

        return $rows;
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @param  array<string, Tenant>  $tenants
     * @return array{0: list<DashboardAlertData>, 1: int}
     */
    private function unassignedJobs(array $visibilities, array $tenants, Carbon $today, int $limit): array
    {
        $ids = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->scheduleAll);

        if ($ids === []) {
            return [[], 0];
        }

        $horizon = $this->intConfig('dashboard.unassigned_horizon_days', 7);
        $windowEnd = $today->copy()->addDays($horizon - 1)->toDateString();

        $base = ScheduledJob::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $ids)
            ->where('status', JobStatusEnum::Unassigned->value)
            ->whereBetween('scheduled_date', [$today->toDateString(), $windowEnd]);

        $count = (clone $base)->count();

        $rows = array_values((clone $base)
            ->with([
                'cleaningObject' => fn (Relation $relation) => $relation->withoutGlobalScope(TenantScope::class)->select('id', 'name', 'client_id'),
                'cleaningObject.client' => fn (Relation $relation) => $relation->withoutGlobalScope(TenantScope::class)->select('id', 'name'),
            ])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get()
            ->map(fn (ScheduledJob $job) => DashboardAlertData::fromJob($job, $tenants[$job->tenant_id]))
            ->all());

        return [$rows, $count];
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @param  array<string, Tenant>  $tenants
     * @return array{0: list<DashboardAlertData>, 1: int}
     */
    private function expiringContracts(array $visibilities, array $tenants, Carbon $today, int $limit): array
    {
        $ids = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->contracts);

        if ($ids === []) {
            return [[], 0];
        }

        $windowEnd = $today->copy()->addDays($this->maxNoticeDays('contracts.expiring_notice_days', 30))->toDateString();

        // Q3 override: only customer service agreements count toward the expiring metric
        // and its alert feed — employment/NDA/GDPR/other contracts never surface here.
        $base = Contract::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $ids)
            ->where('status', ContractStatusEnum::Active->value)
            ->where('term_type', ContractTermTypeEnum::Fixed->value)
            ->where('category', ContractCategoryEnum::ServiceAgreement->value)
            ->where('contractable_type', ContractableTypeEnum::CleaningObject->value)
            ->whereBetween('end_date', [$today->toDateString(), $windowEnd]);

        $count = (clone $base)->count();

        $rows = array_values((clone $base)
            ->with(['contractable' => function (MorphTo|Relation $relation): void {
                /** @var MorphTo<Model, Contract> $relation */
                $relation->constrain([
                    CleaningObject::class => fn (Builder $q) => $q->withoutGlobalScope(TenantScope::class),
                ]);
            }])
            ->orderBy('end_date')
            ->limit($limit)
            ->get()
            ->map(fn (Contract $contract) => DashboardAlertData::fromContract($contract, $tenants[$contract->tenant_id]))
            ->all());

        return [$rows, $count];
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @param  array<string, Tenant>  $tenants
     * @return array{0: list<DashboardAlertData>, 1: int}
     */
    private function expiringQuotes(array $visibilities, array $tenants, Carbon $today, int $limit): array
    {
        $ids = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->quotes);

        if ($ids === []) {
            return [[], 0];
        }

        $windowEnd = $today->copy()->addDays($this->maxNoticeDays('quotes.expiring_notice_days', 7))->toDateString();

        $base = Quote::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $ids)
            ->where('status', QuoteStatusEnum::Sent->value)
            ->where('kind', QuoteKindEnum::Itemized->value)
            ->whereBetween('valid_until', [$today->toDateString(), $windowEnd]);

        $count = (clone $base)->count();

        $rows = array_values((clone $base)
            ->orderBy('valid_until')
            ->limit($limit)
            ->get()
            ->map(fn (Quote $quote) => DashboardAlertData::fromQuote($quote, $tenants[$quote->tenant_id]))
            ->all());

        return [$rows, $count];
    }

    /** Max of a `list<int>` notice-days config, falling back when the config is empty. */
    private function maxNoticeDays(string $key, int $fallback): int
    {
        /** @var list<int> $days */
        $days = config($key, [$fallback]);

        return $days === [] ? $fallback : max($days);
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }
}
