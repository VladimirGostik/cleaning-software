<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

final class TenantPolicy
{
    /**
     * No `create` ability — a brand-new tenant has no roles yet, so a permission
     * check would be circular. `POST /tenants` is deliberately auth-only (D4a).
     */
    public function switchTo(User $user, Tenant $tenant): bool
    {
        return $user->hasActiveMembership($tenant->id) && $tenant->is_active;
    }
}
