<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Expense;
use App\Models\User;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;

final class ExpensePolicy
{
    public function __construct(private readonly TenantPermissionSnapshot $snapshot) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewExpenses->value);
    }

    public function viewAnyAcrossCompanies(User $user): bool
    {
        return array_any($this->snapshot->forRequest($user), fn (TenantVisibility $visibility): bool => $visibility->expenses);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can(PermissionEnum::ViewExpenses->value) && $this->mayTouchEmployeeData($user, $expense);
    }

    public function createForEmployee(User $user, string $tenantId): bool
    {
        return $this->create($user, $tenantId) && $this->readsEmployeeData($user, $tenantId);
    }

    /** `$tenantId` null asks "may this user manage expenses somewhere"; any other tenant is checked from the snapshot. */
    public function create(User $user, ?string $tenantId = null): bool
    {
        if (($tenantId === null || $tenantId === $this->boundTenantId()) && $user->can(PermissionEnum::ManageExpenses->value)) {
            return true;
        }

        return array_any(
            $this->snapshot->forRequest($user),
            fn (TenantVisibility $visibility): bool => ($tenantId === null || $visibility->tenant->id === $tenantId) && $visibility->manageExpenses,
        );
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can(PermissionEnum::ManageExpenses->value) && $this->mayTouchEmployeeData($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->can(PermissionEnum::ManageExpenses->value) && $this->mayTouchEmployeeData($user, $expense);
    }

    public function confirm(User $user, Expense $expense): bool
    {
        return $user->can(PermissionEnum::ManageExpenses->value) && $this->mayTouchEmployeeData($user, $expense);
    }

    public function import(User $user): bool
    {
        return $user->can(PermissionEnum::ManageExpenses->value);
    }

    private function mayTouchEmployeeData(User $user, Expense $expense): bool
    {
        return $expense->tenant_membership_id === null || $this->readsEmployeeData($user, $expense->tenant_id);
    }

    private function readsEmployeeData(User $user, string $tenantId): bool
    {
        if ($tenantId === $this->boundTenantId() && $user->can(PermissionEnum::ViewEmployeePersonalData->value)) {
            return true;
        }

        return array_any(
            $this->snapshot->forRequest($user),
            static fn (TenantVisibility $visibility): bool => $visibility->tenant->id === $tenantId
                && in_array(PermissionEnum::ViewEmployeePersonalData, $visibility->permissions, true),
        );
    }

    private function boundTenantId(): ?string
    {
        /** @var string|null */
        return app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
    }
}
