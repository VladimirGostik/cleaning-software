<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use Database\Factories\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property bool $is_active
 * @property Carbon $joined_at
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property string|null $position
 * @property User|null $user
 * @property Tenant|null $tenant
 */
#[Fillable(['user_id', 'tenant_id', 'is_active', 'joined_at', 'first_name', 'last_name', 'phone', 'position'])]
final class TenantMembership extends Model
{
    /** @use HasFactory<TenantMembershipFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_active', 'position', 'first_name', 'last_name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
