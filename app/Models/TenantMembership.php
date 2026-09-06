<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\ContractCategoryEnum;
use Database\Factories\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
 * @property string $display_name
 * @property User|null $user
 * @property Tenant|null $tenant
 * @property Collection<int, Contract> $employmentContracts
 * @property Collection<int, ScheduledJob> $scheduledJobs
 * @property int|null $upcoming_jobs_count
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

    /** @return HasMany<ScheduledJob, $this> */
    public function scheduledJobs(): HasMany
    {
        return $this->hasMany(ScheduledJob::class, 'assigned_membership_id');
    }

    /** @return MorphMany<Contract, $this> */
    public function employmentContracts(): MorphMany
    {
        return $this->morphMany(Contract::class, 'contractable')
            ->where('category', ContractCategoryEnum::Employment->value);
    }

    /** @return Attribute<string, never> */
    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

            if ($name !== '') {
                return $name;
            }

            return $this->user->name ?? $this->user->email ?? '';
        });
    }
}
