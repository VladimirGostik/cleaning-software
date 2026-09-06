<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * @property string|null $tenant_id
 */
final class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        self::creating(function (self $activity): void {
            if ($activity->tenant_id === null && app()->bound('current_tenant_id')) {
                $activity->tenant_id = current_tenant_id();
            }
        });
    }

    /**
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopeVisibleInTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where(function (Builder $q) use ($tenantId): void {
            $q->where('tenant_id', $tenantId)
                ->orWhere(function (Builder $inner) use ($tenantId): void {
                    $inner->whereNull('tenant_id')
                        ->whereHasMorph('causer', [User::class], function (Builder $causer) use ($tenantId): void {
                            $causer->whereHas('memberships', fn (Builder $m) => $m->where('tenant_id', $tenantId));
                        });
                });
        });
    }

    public function isVisibleInTenant(string $tenantId): bool
    {
        if ($this->tenant_id === $tenantId) {
            return true;
        }

        if ($this->tenant_id !== null) {
            return false;
        }

        $causer = $this->causer;

        return $causer instanceof User && $causer->isMemberOf($tenantId);
    }
}
