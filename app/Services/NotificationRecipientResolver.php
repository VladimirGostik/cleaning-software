<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

final readonly class NotificationRecipientResolver
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * Active members of `$tenantId` (membership + user both active) holding `$permission`
     * in that tenant (direct or via role). Single query — Spatie `scopePermission` joins
     * direct + role permissions, role pivot team-scoped by the registrar's team id.
     *
     * @return Collection<int, User>
     */
    public function usersWithPermission(string $tenantId, PermissionEnum $permission): Collection
    {
        $previous = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($tenantId);

        try {
            return User::query()
                ->where('is_active', true)
                ->whereHas('memberships', fn (Builder $query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true))
                ->permission($permission->value)
                ->get();
        } finally {
            $this->registrar->setPermissionsTeamId($previous);
        }
    }
}
