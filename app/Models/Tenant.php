<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Contracts\ChecksFeatures;
use App\Enums\FeatureEnum;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property TenantInterface|null $interface
 */
#[Fillable([
    'owner_id',
    'name',
    'ico',
    'dic',
    'vat_number',
    'is_vat_payer',
    'vat_rate',
    'iban',
    'swift_bic',
    'invoice_number_format',
    'registration_info',
    'address_line',
    'city',
    'postal_code',
    'country',
    'contact_email',
    'contact_phone',
    'is_active',
])]
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_vat_payer' => 'boolean',
            'is_active' => 'boolean',
            'vat_rate' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'owner_id', 'name', 'ico', 'dic', 'vat_number', 'is_vat_payer',
                'vat_rate', 'iban', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function hasFeature(FeatureEnum $feature): bool
    {
        return app(ChecksFeatures::class)->hasFeature($this, $feature);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function interface(): HasOne
    {
        return $this->hasOne(TenantInterface::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_memberships')
            ->withPivot(['is_active', 'joined_at'])
            ->withTimestamps();
    }
}
