<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string|null $locale
 * @property bool $is_active
 * @property array<string, array<string, bool>>|null $notification_preferences
 * @property Carbon $created_at
 * @property-read Collection<int, Role> $roles
 */
#[Fillable(['name', 'email', 'phone', 'password', 'locale', 'is_active', 'notification_preferences'])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, LogsActivity, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'locale', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function preferredLocale(): string
    {
        return $this->locale ?? config('app.locale');
    }

    /**
     * @return HasMany<TenantMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->withPivot(['is_active', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Tenant, $this>
     */
    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    /** Id of the actor's active membership in the bound tenant; null outside tenant context (jobs, console). */
    public function activeMembershipId(): ?string
    {
        if (! app()->bound('current_tenant_id')) {
            return null;
        }

        $tenantId = app('current_tenant_id');

        return $this->memberships()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->value('id');
    }
}
