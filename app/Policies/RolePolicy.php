<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;

final class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view roles');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('view roles');
    }

    public function create(User $user): bool
    {
        return $user->can('create roles');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('edit roles');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('delete roles') && ! in_array($role->name, RoleService::SYSTEM_ROLES, true);
    }
}
