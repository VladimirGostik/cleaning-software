<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Data\Dashboard\InvoiceCurrencyMetricsData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\JobStatusEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\ScheduledJob;
use App\Models\TenantMembership;
use App\Scopes\TenantScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Grouped cross-tenant aggregate reads behind the dashboard metric cards. Every query
 * removes `TenantScope` only (never `withoutGlobalScopes()`) so `SoftDeletes` / `is_active`
 * business filters keep applying. Missing tenant in a grouped result = 0, never null — the
 * permission-driven null decision lives in `DashboardService`, not here.
 */
final readonly class DashboardMetricsQuery
{
    /**
     * @param  list<TenantVisibility>  $visibilities
     * @return array{
     *     invoices: array<string, list<InvoiceCurrencyMetricsData>>,
     *     schedule: array<string, array{today: int, this_week: int, unassigned: int}>,
     *     contracts: array<string, array{active: int, expiring: int}>,
     *     quotes: array<string, int>,
     *     employees: array<string, int>,
     *     clients: array<string, int>,
     *     objects: array<string, int>,
     * }
     */
    public function collect(array $visibilities): array
    {
        $today = Carbon::today();

        return [
            'invoices' => $this->invoices($visibilities, $today),
            'schedule' => $this->schedule($visibilities, $today),
            'contracts' => $this->contracts($visibilities, $today),
            'quotes' => $this->quotes($visibilities),
            'employees' => $this->employees($visibilities),
            'clients' => $this->clients($visibilities),
            'objects' => $this->objects($visibilities),
        ];
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @return array<string, list<InvoiceCurrencyMetricsData>>
     */
    private function invoices(array $visibilities, Carbon $today): array
    {
        $ids = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->invoices);

        /** @var array<string, list<InvoiceCurrencyMetricsData>> $result */
        $result = [];

        if ($ids === []) {
            return $result;
        }

        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();
        $issued = InvoiceStatusEnum::Issued->value;
        $overdue = InvoiceStatusEnum::Overdue->value;
        $paid = InvoiceStatusEnum::Paid->value;

        $rows = Invoice::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $ids)
            ->whereNull('credited_invoice_id')
            ->groupBy('tenant_id', 'currency')
            ->selectRaw(
                'tenant_id, currency,'
                .'COUNT(*) FILTER (WHERE status IN (?, ?, ?) AND issue_date BETWEEN ? AND ?) AS invoiced_month_count,'
                .'COALESCE(SUM(total) FILTER (WHERE status IN (?, ?, ?) AND issue_date BETWEEN ? AND ?), 0) AS invoiced_month_sum,'
                .'COUNT(*) FILTER (WHERE status = ?) AS unpaid_count,'
                .'COALESCE(SUM(total) FILTER (WHERE status = ?), 0) AS unpaid_sum,'
                .'COUNT(*) FILTER (WHERE status = ?) AS overdue_count,'
                .'COALESCE(SUM(total) FILTER (WHERE status = ?), 0) AS overdue_sum',
                [
                    $issued, $overdue, $paid, $monthStart, $monthEnd,
                    $issued, $overdue, $paid, $monthStart, $monthEnd,
                    $issued, $issued, $overdue, $overdue,
                ],
            )
            ->get();

        /** @var array<string, list<InvoiceCurrencyMetricsData>> $byTenant */
        $byTenant = [];

        foreach ($rows as $row) {
            $tenantId = $this->stringAttribute($row, 'tenant_id');
            $currency = $this->currencyAttribute($row);

            $byTenant[$tenantId][] = InvoiceCurrencyMetricsData::fromRow(
                $currency,
                $this->intAttribute($row, 'invoiced_month_count'),
                $this->floatAttribute($row, 'invoiced_month_sum'),
                $this->intAttribute($row, 'unpaid_count'),
                $this->floatAttribute($row, 'unpaid_sum'),
                $this->intAttribute($row, 'overdue_count'),
                $this->floatAttribute($row, 'overdue_sum'),
            );
        }

        foreach ($visibilities as $visibility) {
            if (! $visibility->invoices) {
                continue;
            }

            $tenantId = $visibility->tenant->id;
            $default = $visibility->tenant->interface->default_currency ?? CurrencyEnum::EUR;
            $result[$tenantId] = $this->withDefaultCurrencyFirst($byTenant[$tenantId] ?? [], $default);
        }

        return $result;
    }

    /**
     * @param  list<InvoiceCurrencyMetricsData>  $rows
     * @return list<InvoiceCurrencyMetricsData>
     */
    private function withDefaultCurrencyFirst(array $rows, CurrencyEnum $default): array
    {
        /** @var array<string, InvoiceCurrencyMetricsData> $byCurrency */
        $byCurrency = [];

        foreach ($rows as $row) {
            $byCurrency[$row->currency->value] = $row;
        }

        $defaultRow = $byCurrency[$default->value] ?? InvoiceCurrencyMetricsData::zero($default);
        unset($byCurrency[$default->value]);

        // Non-default currency rows only surface data-backed (invoiced/unpaid/overdue) activity —
        // draft/cancelled-only invoices in a foreign currency group by currency in the base query
        // but never match any status FILTER, so they'd otherwise leak an all-zero row.
        $byCurrency = array_filter($byCurrency, fn (InvoiceCurrencyMetricsData $row): bool => ! $this->isZeroMetrics($row));

        ksort($byCurrency);

        return [$defaultRow, ...array_values($byCurrency)];
    }

    private function isZeroMetrics(InvoiceCurrencyMetricsData $row): bool
    {
        return $row->invoiced_month_count === 0
            && $row->unpaid_count === 0
            && $row->overdue_count === 0;
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @return array<string, array{today: int, this_week: int, unassigned: int}>
     */
    private function schedule(array $visibilities, Carbon $today): array
    {
        $allIds = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->schedule);

        /** @var array<string, array{today: int, this_week: int, unassigned: int}> $result */
        $result = array_fill_keys($allIds, ['today' => 0, 'this_week' => 0, 'unassigned' => 0]);

        if ($allIds === []) {
            return $result;
        }

        $fullIds = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->schedule && $v->scheduleAll);
        $ownVisibilities = array_values(array_filter(
            $visibilities,
            fn (TenantVisibility $v): bool => $v->schedule && ! $v->scheduleAll,
        ));
        $ownIds = array_map(fn (TenantVisibility $v): string => $v->tenant->id, $ownVisibilities);
        /** @var list<string> $ownMembershipIds */
        $ownMembershipIds = array_values(array_filter(
            array_map(fn (TenantVisibility $v): ?string => $v->membershipId, $ownVisibilities),
        ));

        $weekStart = $today->copy()->startOfWeek(CarbonInterface::MONDAY)->toDateString();
        $weekEnd = $today->copy()->endOfWeek(CarbonInterface::SUNDAY)->toDateString();

        $rows = ScheduledJob::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', '<>', JobStatusEnum::Cancelled->value)
            ->whereBetween('scheduled_date', [$weekStart, $weekEnd])
            ->where(function (Builder $query) use ($fullIds, $ownIds, $ownMembershipIds): void {
                $query->whereIn('tenant_id', $fullIds)
                    ->orWhere(function (Builder $inner) use ($ownIds, $ownMembershipIds): void {
                        $inner->whereIn('tenant_id', $ownIds)->whereIn('assigned_membership_id', $ownMembershipIds);
                    });
            })
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, COUNT(*) FILTER (WHERE scheduled_date = ?) AS today, COUNT(*) AS this_week', [$today->toDateString()])
            ->get();

        foreach ($rows as $row) {
            $tenantId = $this->stringAttribute($row, 'tenant_id');
            $entry = $result[$tenantId] ?? ['today' => 0, 'this_week' => 0, 'unassigned' => 0];
            $entry['today'] = $this->intAttribute($row, 'today');
            $entry['this_week'] = $this->intAttribute($row, 'this_week');
            $result[$tenantId] = $entry;
        }

        if ($fullIds !== []) {
            $horizon = $this->intConfig('dashboard.unassigned_horizon_days', 7);
            $unassignedEnd = $today->copy()->addDays($horizon - 1)->toDateString();

            $unassignedRows = ScheduledJob::query()
                ->withoutGlobalScope(TenantScope::class)
                ->whereIn('tenant_id', $fullIds)
                ->where('status', JobStatusEnum::Unassigned->value)
                ->whereBetween('scheduled_date', [$today->toDateString(), $unassignedEnd])
                ->groupBy('tenant_id')
                ->selectRaw('tenant_id, COUNT(*) AS unassigned')
                ->get();

            foreach ($unassignedRows as $row) {
                $tenantId = $this->stringAttribute($row, 'tenant_id');
                $entry = $result[$tenantId] ?? ['today' => 0, 'this_week' => 0, 'unassigned' => 0];
                $entry['unassigned'] = $this->intAttribute($row, 'unassigned');
                $result[$tenantId] = $entry;
            }
        }

        return $result;
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @return array<string, array{active: int, expiring: int}>
     */
    private function contracts(array $visibilities, Carbon $today): array
    {
        $ids = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->contracts);

        /** @var array<string, array{active: int, expiring: int}> $result */
        $result = array_fill_keys($ids, ['active' => 0, 'expiring' => 0]);

        if ($ids === []) {
            return $result;
        }

        $windowEnd = $today->copy()->addDays($this->maxNoticeDays('contracts.expiring_notice_days', 30))->toDateString();
        $active = ContractStatusEnum::Active->value;

        $rows = Contract::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $ids)
            ->where('category', ContractCategoryEnum::ServiceAgreement->value)
            ->where('contractable_type', ContractableTypeEnum::CleaningObject->value)
            ->groupBy('tenant_id')
            ->selectRaw(
                'tenant_id,'
                .'COUNT(*) FILTER (WHERE status = ?) AS active,'
                .'COUNT(*) FILTER (WHERE status = ? AND term_type = ? AND end_date BETWEEN ? AND ?) AS expiring',
                [$active, $active, ContractTermTypeEnum::Fixed->value, $today->toDateString(), $windowEnd],
            )
            ->get();

        foreach ($rows as $row) {
            $tenantId = $this->stringAttribute($row, 'tenant_id');
            $result[$tenantId] = [
                'active' => $this->intAttribute($row, 'active'),
                'expiring' => $this->intAttribute($row, 'expiring'),
            ];
        }

        return $result;
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @return array<string, int>
     */
    private function quotes(array $visibilities): array
    {
        $ids = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->quotes);

        /** @var array<string, int> $result */
        $result = array_fill_keys($ids, 0);

        if ($ids === []) {
            return $result;
        }

        $rows = Quote::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $ids)
            ->where('status', QuoteStatusEnum::Sent->value)
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, COUNT(*) AS awaiting')
            ->get();

        foreach ($rows as $row) {
            $result[$this->stringAttribute($row, 'tenant_id')] = $this->intAttribute($row, 'awaiting');
        }

        return $result;
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @return array<string, int>
     */
    private function employees(array $visibilities): array
    {
        $ids = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->employees);

        /** @var array<string, int> $result */
        $result = array_fill_keys($ids, 0);

        if ($ids === []) {
            return $result;
        }

        $rows = TenantMembership::query()
            ->whereIn('tenant_id', $ids)
            ->where('is_active', true)
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, COUNT(*) AS active_count')
            ->get();

        foreach ($rows as $row) {
            $result[$this->stringAttribute($row, 'tenant_id')] = $this->intAttribute($row, 'active_count');
        }

        return $result;
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @return array<string, int>
     */
    private function clients(array $visibilities): array
    {
        $ids = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->clients);

        /** @var array<string, int> $result */
        $result = array_fill_keys($ids, 0);

        if ($ids === []) {
            return $result;
        }

        $rows = Client::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $ids)
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, COUNT(*) AS total')
            ->get();

        foreach ($rows as $row) {
            $result[$this->stringAttribute($row, 'tenant_id')] = $this->intAttribute($row, 'total');
        }

        return $result;
    }

    /**
     * @param  list<TenantVisibility>  $visibilities
     * @return array<string, int>
     */
    private function objects(array $visibilities): array
    {
        $ids = TenantVisibility::idsWhere($visibilities, fn (TenantVisibility $v): bool => $v->objects);

        /** @var array<string, int> $result */
        $result = array_fill_keys($ids, 0);

        if ($ids === []) {
            return $result;
        }

        $rows = CleaningObject::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $ids)
            ->where('is_active', true)
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, COUNT(*) AS total')
            ->get();

        foreach ($rows as $row) {
            $result[$this->stringAttribute($row, 'tenant_id')] = $this->intAttribute($row, 'total');
        }

        return $result;
    }

    /** Max of a `list<int>` notice-days config, falling back when the config is empty. */
    private function maxNoticeDays(string $key, int $fallback): int
    {
        /** @var list<int> $days */
        $days = config($key, [$fallback]);

        return $days === [] ? $fallback : max($days);
    }

    private function stringAttribute(Model $row, string $key): string
    {
        $value = $row->getAttribute($key);

        return is_string($value) ? $value : '';
    }

    private function intAttribute(Model $row, string $key): int
    {
        $value = $row->getAttribute($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    private function floatAttribute(Model $row, string $key): float
    {
        $value = $row->getAttribute($key);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }

    private function currencyAttribute(Model $row): CurrencyEnum
    {
        $value = $row->getAttribute('currency');

        return $value instanceof CurrencyEnum ? $value : CurrencyEnum::EUR;
    }
}
