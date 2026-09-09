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

    /** The causer as a User, or null when the activity has no user causer. */
    public function causerUser(): ?User
    {
        $causer = $this->causer;

        return $causer instanceof User ? $causer : null;
    }

    /** @return array<string, mixed>|null */
    public function propertiesArray(): ?array
    {
        /** @var array<string, mixed>|null */
        return $this->properties?->toArray();
    }

    /** @return array<string, mixed>|null */
    public function attributeChangesArray(): ?array
    {
        /** @var array<string, mixed>|null */
        return $this->attribute_changes?->toArray();
    }

    public function isVisibleInTenant(string $tenantId): bool
    {
        if ($this->tenant_id === $tenantId) {
            return true;
        }

        if ($this->tenant_id !== null) {
            return false;
        }

        $causer = $this->causerUser();

        return $causer !== null && $causer->isMemberOf($tenantId);
    }
}
