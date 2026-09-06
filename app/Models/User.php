<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use Database\Factories\UserFactory;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'locale', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use CanResetPasswordTrait, HasApiTokens, HasFactory, HasRoles, HasUuids, LogsActivity, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'locale', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return HasMany<TenantMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /** @return BelongsToMany<Tenant, $this> */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->withPivot(['is_active', 'joined_at'])
            ->withTimestamps();
    }

    /** @return HasMany<Tenant, $this> */
    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    /** Any active membership, or a specific tenant's active membership when `$tenantId` is given. */
    public function hasActiveMembership(?string $tenantId = null): bool
    {
        return $this->memberships()
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->exists();
    }

    /** Membership of any status (active or deactivated) in the given tenant. */
    public function isMemberOf(string $tenantId): bool
    {
        return $this->memberships()->where('tenant_id', $tenantId)->exists();
    }
}
