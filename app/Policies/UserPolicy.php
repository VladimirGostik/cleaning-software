<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Tenant;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewEmployees->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::ViewEmployees->value)
            && $model->isMemberOf(current_tenant_id());
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateEmployees->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::EditEmployees->value)
            && $model->isMemberOf(current_tenant_id());
    }

    public function delete(User $user, User $model): bool
    {
        $tenantId = current_tenant_id();

        if (! $user->can(PermissionEnum::DeleteEmployees->value) || ! $model->isMemberOf($tenantId)) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        $tenant = Tenant::find($tenantId);

        return $tenant === null || $tenant->owner_id !== $model->id;
    }
}
