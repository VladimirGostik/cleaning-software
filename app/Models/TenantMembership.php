<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\ContractCategoryEnum;
use Database\Factories\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property User|null $user
 * @property string $display_name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property string|null $position
 */
#[Fillable(['user_id', 'tenant_id', 'is_active', 'joined_at', 'first_name', 'last_name', 'phone', 'position'])]
final class TenantMembership extends Model
{
    /** @use HasFactory<TenantMembershipFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /**
     * @return array<string, string>
     */
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Employment contracts where this membership is the contractable subject. */
    public function employmentContracts(): MorphMany
    {
        return $this->morphMany(Contract::class, 'contractable')
            ->where('category', ContractCategoryEnum::Employment->value);
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));

            return $name !== '' ? $name : ($this->user?->name ?? $this->user?->email ?? '');
        });
    }
}
