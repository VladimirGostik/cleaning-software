<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'name',
    'ico',
    'dic',
    'vat_number',
    'is_vat_payer',
    'vat_rate',
    'iban',
    'invoice_number_format',
    'address_line',
    'city',
    'postal_code',
    'country',
    'contact_email',
    'contact_phone',
    'subscription_plan',
    'is_active',
])]
final class Tenant extends Model
{
    use HasUuids, LogsActivity, SoftDeletes;

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
                'name', 'ico', 'dic', 'vat_number', 'is_vat_payer',
                'vat_rate', 'iban', 'subscription_plan', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
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
