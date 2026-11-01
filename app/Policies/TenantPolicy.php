<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Illuminate\Auth\Access\Response;

final class TenantPolicy
{
    public function __construct(private readonly TenantPermissionSnapshot $snapshot) {}

    /** The tenant is one the caller's token may act in (mobile access for mobile tokens); the denial keeps the mobile_forbidden shape. */
    public function actIn(User $user, string $companyId): Response
    {
        return $this->snapshot->allowsCompany($user, $companyId) ? Response::allow() : Response::deny(__('app.mobile_forbidden'));
    }

    /**
     * A brand-new tenant has no roles yet, so a permission check would be circular;
     * firm creation is gated by the platform-admin flag instead.
     */
    public function create(User $user): bool
    {
        return $user->is_platform_admin;
    }

    public function switchTo(User $user, Tenant $tenant): bool
    {
        return $user->hasActiveMembership($tenant->id) && $tenant->is_active;
    }
}
