<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string|null $tenant_id
 */
final class Role extends SpatieRole
{
    use HasUuids, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'guard_name', 'tenant_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function isSystem(): bool
    {
        return $this->name === RoleTemplatesSeeder::ADMIN_ROLE;
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return $query->where('name', $operator, "%{$search}%");
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeInTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
