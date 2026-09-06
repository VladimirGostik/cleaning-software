<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;

final class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewRoles->value);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(PermissionEnum::ViewRoles->value) && $role->tenant_id === app('current_tenant_id');
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateRoles->value);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(PermissionEnum::EditRoles->value) && $role->tenant_id === app('current_tenant_id');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(PermissionEnum::DeleteRoles->value)
            && $role->tenant_id === app('current_tenant_id')
            && ! in_array($role->name, RoleService::SYSTEM_ROLES, true);
    }
}
