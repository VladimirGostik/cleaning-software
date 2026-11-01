<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

final class TenantPolicy
{
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
