<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Tenant;
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
        return $user->can(PermissionEnum::ViewEmployees->value) && $this->sameTenant($membership);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateEmployees->value);
    }

    public function update(User $user, TenantMembership $membership): bool
    {
        return $user->can(PermissionEnum::EditEmployees->value) && $this->sameTenant($membership);
    }

    public function delete(User $user, TenantMembership $membership): bool
    {
        if (! $user->can(PermissionEnum::DeleteEmployees->value) || ! $this->sameTenant($membership)) {
            return false;
        }

        if ($membership->user_id === $user->id) {
            return false;
        }

        return $membership->user_id !== $this->ownerId($membership);
    }

    public function reactivate(User $user, TenantMembership $membership): bool
    {
        return $this->update($user, $membership);
    }

    public function assignRole(User $user, TenantMembership $membership): bool
    {
        return $user->can(PermissionEnum::AssignEmployees->value)
            && $this->sameTenant($membership)
            && $membership->hasAppAccess();
    }

    public function manageAccess(User $user): bool
    {
        return $user->can(PermissionEnum::ManageEmployeeAccess->value);
    }

    public function grantAccess(User $user, TenantMembership $membership): bool
    {
        return $this->manageAccess($user)
            && $this->sameTenant($membership)
            && $membership->is_active
            && ! $membership->hasAppAccess();
    }

    public function revokeAccess(User $user, TenantMembership $membership): bool
    {
        if (! $this->manageAccess($user) || ! $this->sameTenant($membership) || ! $membership->hasAppAccess()) {
            return false;
        }

        if ($membership->user_id === $user->id) {
            return false;
        }

        return $membership->user_id !== $this->ownerId($membership);
    }

    public function resendInvitation(User $user, TenantMembership $membership): bool
    {
        $membership->loadMissing('user');

        return $this->manageAccess($user)
            && $this->sameTenant($membership)
            && $membership->is_active
            && $membership->isInvitationPending();
    }

    private function sameTenant(TenantMembership $membership): bool
    {
        return app()->bound('current_tenant_id') && $membership->tenant_id === app('current_tenant_id');
    }

    private function ownerId(TenantMembership $membership): ?string
    {
        /** @var string|null $ownerId */
        $ownerId = Tenant::query()->whereKey($membership->tenant_id)->value('owner_id');

        return $ownerId;
    }
}
