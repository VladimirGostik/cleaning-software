<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Dashboard\CompanyOverviewData;
use App\Data\Dashboard\ContractsMetricsData;
use App\Data\Dashboard\DashboardAlertCountData;
use App\Data\Dashboard\DashboardData;
use App\Data\Dashboard\InvoiceCurrencyMetricsData;
use App\Data\Dashboard\InvoiceMetricsData;
use App\Data\Dashboard\PeopleMetricsData;
use App\Data\Dashboard\ScheduleMetricsData;
use App\Enums\CurrencyEnum;
use App\Enums\DashboardAlertTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\User;
use App\Services\Dashboard\DashboardAlertsQuery;
use App\Services\Dashboard\DashboardMetricsQuery;
use App\Services\Dashboard\TenantVisibility;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cross-company "Prehľad" read model. Read-only — no `DB::transaction`, no `#[Authorize]`
 * (no dedicated permission; every metric group is nulled per missing per-tenant permission,
 * ADR ref: `.claude/plans/dashboard-overview.md` hard gate 6).
 */
final readonly class DashboardService
{
    public function __construct(
        private PermissionRegistrar $registrar,
        private DashboardMetricsQuery $metrics,
        private DashboardAlertsQuery $alerts,
    ) {}

    public function overviewFor(User $user): DashboardData
    {
        $visibilities = $this->visibilityFor($user);

        if ($visibilities === []) {
            return new DashboardData(
                companies: [],
                alerts: [],
                alert_counts: $this->emptyAlertCounts(),
                generated_at: now()->toIso8601String(),
            );
        }

        $metrics = $this->metrics->collect($visibilities);
        $alerts = $this->alerts->collect($visibilities);

        $companies = array_map(
            fn (TenantVisibility $visibility): CompanyOverviewData => $this->companyFor($visibility, $metrics),
            $visibilities,
        );

        $alertCounts = array_map(
            fn (DashboardAlertTypeEnum $type): DashboardAlertCountData => new DashboardAlertCountData(
                $type,
                $alerts['counts'][$type->value] ?? 0,
            ),
            DashboardAlertTypeEnum::cases(),
        );

        return new DashboardData(
            companies: $companies,
            alerts: $alerts['rows'],
            alert_counts: $alertCounts,
            generated_at: now()->toIso8601String(),
        );
    }

    /**
     * ADR-D3: reuses Spatie's own permission resolution per tenant (direct + role
     * permissions) instead of hand-rolled joins. Switching the registrar team id mid-request
     * requires dropping the cached `roles` / `permissions` relations on `$user`, both while
     * iterating AND after restoring the previous team id — `HandleInertiaRequests::share`'s
     * `can` / `navigation` closures run after this method returns and must see the active
     * tenant's permissions, not the last tenant iterated here.
     *
     * @return list<TenantVisibility>
     */
    private function visibilityFor(User $user): array
    {
        $tenants = $user->tenants()
            ->wherePivot('is_active', true)
            ->where('tenants.is_active', true)
            ->with('interface')
            ->orderBy('tenants.name')
            ->get();

        if ($tenants->isEmpty()) {
            return [];
        }

        $memberships = $user->memberships()
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('tenant_id');

        /** @var list<TenantVisibility> $visibilities */
        $visibilities = [];
        $previous = $this->registrar->getPermissionsTeamId();

        try {
            foreach ($tenants as $tenant) {
                $this->registrar->setPermissionsTeamId($tenant->id);
                $user->unsetRelation('roles')->unsetRelation('permissions');

                $can = fn (PermissionEnum $permission): bool => $user->can($permission->value);
                $membership = $memberships->get($tenant->id);

                $visibilities[] = new TenantVisibility(
                    tenant: $tenant,
                    membershipId: $membership?->id,
                    invoices: $can(PermissionEnum::ViewInvoices),
                    schedule: $can(PermissionEnum::ViewSchedule),
                    scheduleAll: $can(PermissionEnum::ViewSchedule) && $can(PermissionEnum::ViewAllSchedule),
                    contracts: $can(PermissionEnum::ViewContracts),
                    quotes: $can(PermissionEnum::ViewQuotes),
                    employees: $can(PermissionEnum::ViewEmployees),
                    clients: $can(PermissionEnum::ViewClients),
                    objects: $can(PermissionEnum::ViewObjects) && $can(PermissionEnum::ViewAllObjects),
                    billingSettings: $can(PermissionEnum::ManageBillingSettings),
                    createInvoices: $can(PermissionEnum::CreateInvoices),
                );
            }
        } finally {
            $this->registrar->setPermissionsTeamId($previous);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        return $visibilities;
    }

    /**
     * @param  array{
     *     invoices: array<string, list<InvoiceCurrencyMetricsData>>,
     *     schedule: array<string, array{today: int, this_week: int, unassigned: int}>,
     *     contracts: array<string, array{active: int, expiring: int}>,
     *     quotes: array<string, int>,
     *     employees: array<string, int>,
     *     clients: array<string, int>,
     *     objects: array<string, int>,
     * }  $metrics
     */
    private function companyFor(TenantVisibility $visibility, array $metrics): CompanyOverviewData
    {
        $tenant = $visibility->tenant;
        $tenantId = $tenant->id;

        return new CompanyOverviewData(
            tenant_id: $tenantId,
            name: $tenant->name,
            color: $tenant->interface?->color,
            is_current: $tenantId === current_tenant_id(),
            supplier_complete: $tenant->hasCompleteSupplierProfile(),
            invoices: $visibility->invoices ? new InvoiceMetricsData(
                default_currency: $tenant->interface->default_currency ?? CurrencyEnum::EUR,
                by_currency: $metrics['invoices'][$tenantId] ?? [],
            ) : null,
            schedule: $visibility->schedule ? new ScheduleMetricsData(
                today: $metrics['schedule'][$tenantId]['today'] ?? 0,
                this_week: $metrics['schedule'][$tenantId]['this_week'] ?? 0,
                unassigned_next_7_days: $visibility->scheduleAll ? ($metrics['schedule'][$tenantId]['unassigned'] ?? 0) : null,
                own_only: ! $visibility->scheduleAll,
            ) : null,
            contracts: ($visibility->contracts || $visibility->quotes) ? new ContractsMetricsData(
                active: $visibility->contracts ? ($metrics['contracts'][$tenantId]['active'] ?? 0) : null,
                expiring_30d: $visibility->contracts ? ($metrics['contracts'][$tenantId]['expiring'] ?? 0) : null,
                quotes_awaiting: $visibility->quotes ? ($metrics['quotes'][$tenantId] ?? 0) : null,
            ) : null,
            people: ($visibility->employees || $visibility->clients || $visibility->objects) ? new PeopleMetricsData(
                employees: $visibility->employees ? ($metrics['employees'][$tenantId] ?? 0) : null,
                clients: $visibility->clients ? ($metrics['clients'][$tenantId] ?? 0) : null,
                objects: $visibility->objects ? ($metrics['objects'][$tenantId] ?? 0) : null,
            ) : null,
        );
    }

    /** @return list<DashboardAlertCountData> */
    private function emptyAlertCounts(): array
    {
        return array_map(
            fn (DashboardAlertTypeEnum $type): DashboardAlertCountData => new DashboardAlertCountData($type, 0),
            DashboardAlertTypeEnum::cases(),
        );
    }
}
