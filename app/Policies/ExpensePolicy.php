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
        return $user->can(PermissionEnum::ViewExpenses->value);
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
        return $user->can(PermissionEnum::ManageExpenses->value);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->can(PermissionEnum::ManageExpenses->value);
    }

    public function confirm(User $user, Expense $expense): bool
    {
        return $user->can(PermissionEnum::ManageExpenses->value);
    }

    public function import(User $user): bool
    {
        return $user->can(PermissionEnum::ManageExpenses->value);
    }

    private function boundTenantId(): ?string
    {
        /** @var string|null */
        return app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
    }
}
