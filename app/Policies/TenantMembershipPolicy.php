<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\TenantMembership;
use App\Models\User;

final class TenantMembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewEmployees->value);
    }

    public function view(User $user, TenantMembership $membership): bool
    {
        return $user->can(PermissionEnum::ViewEmployees->value)
            && $membership->tenant_id === app('current_tenant_id');
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateEmployees->value);
    }

    public function update(User $user, TenantMembership $membership): bool
    {
        return $user->can(PermissionEnum::EditEmployees->value)
            && $membership->tenant_id === app('current_tenant_id');
    }

    public function delete(User $user, TenantMembership $membership): bool
    {
        return $user->can(PermissionEnum::DeleteEmployees->value)
            && $membership->tenant_id === app('current_tenant_id');
    }
}
