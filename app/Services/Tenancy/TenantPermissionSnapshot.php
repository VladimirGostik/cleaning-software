<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\PermissionEnum;
use App\Models\Permission;
use App\Models\User;
use App\Services\Dashboard\TenantVisibility;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Spatie\Permission\PermissionRegistrar;

final readonly class TenantPermissionSnapshot
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function hasMobileAccess(User $user, ?string $tenantId = null): bool
    {
        foreach ($this->for($user) as $visibility) {
            if ($visibility->mobileAccess && ($tenantId === null || $visibility->tenant->id === $tenantId)) {
                return true;
            }
        }

        return false;
    }

    public function allowsCompany(User $user, string $companyId): bool
    {
        return array_any($this->forRequest($user), static fn (TenantVisibility $visibility): bool => $visibility->tenant->id === $companyId);
    }

    /**
     * Reads the token from the instance passed in, so callers acting as a user must pass the token-bearing instance.
     *
     * @return list<TenantVisibility>
     */
    public function forRequest(User $user): array
    {
        $all = $this->for($user);
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! $token->can('mobile')) {
            return $all;
        }

        return array_values(array_filter($all, fn (TenantVisibility $visibility): bool => $visibility->mobileAccess));
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
    public function for(User $user): array
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

                /** @var Collection<int, Permission> $granted */
                $granted = $user->getAllPermissions();

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
                    mobileAccess: $can(PermissionEnum::AccessMobileApp),
                    tasks: $can(PermissionEnum::ViewTasks),
                    tasksAll: $can(PermissionEnum::ViewTasks) && $can(PermissionEnum::ViewAllTasks),
                    expenses: $can(PermissionEnum::ViewExpenses),
                    manageExpenses: $can(PermissionEnum::ManageExpenses),
                    permissions: array_values($granted
                        ->map(fn (Permission $permission): ?PermissionEnum => PermissionEnum::tryFrom($permission->name))
                        ->filter()
                        ->all()),
                );
            }
        } finally {
            $this->registrar->setPermissionsTeamId($previous);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        return $visibilities;
    }
}
