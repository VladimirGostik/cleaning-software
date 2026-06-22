<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

final readonly class NotificationRecipientResolver
{
    public function __construct(private PermissionRegistrar $permissionRegistrar) {}

    /**
     * Returns active tenant members who hold the given permission within the tenant.
     *
     * @return Collection<int, User>
     */
    public function usersWithPermission(string $tenantId, PermissionEnum $permission): Collection
    {
        $this->permissionRegistrar->setPermissionsTeamId($tenantId);

        return User::whereHas(
            'memberships',
            fn ($q) => $q->where('tenant_id', $tenantId)->where('is_active', true),
        )
            ->get()
            ->filter(fn (User $user) => $user->can($permission->value))
            ->values();
    }
}
