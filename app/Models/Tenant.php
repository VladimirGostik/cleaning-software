<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
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
 * @property string $id
 * @property string $name
 * @property string|null $ico
 * @property string|null $dic
 * @property string|null $vat_number
 * @property bool $is_vat_payer
 * @property string|null $vat_rate
 * @property string|null $iban
 * @property string|null $swift_bic
 * @property string $invoice_number_format
 * @property string|null $registration_info
 * @property string|null $address_line
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property bool $is_active
 * @property User|null $owner
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

    /** @return array<string, string> */
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
                'swift_bic', 'invoice_number_format', 'registration_info',
                'address_line', 'city', 'postal_code', 'country', 'contact_email', 'contact_phone',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Column names of required supplier fields still empty; [] = complete.
     * Vocabulary is a stable FE contract: name|address_line|city|postal_code|ico|dic|vat_number.
     *
     * @return list<string>
     */
    public function missingSupplierFields(): array
    {
        $required = ['name', 'address_line', 'city', 'postal_code', 'ico'];

        if ($this->is_vat_payer) {
            $required[] = 'dic';
            $required[] = 'vat_number';
        }

        return array_values(array_filter(
            $required,
            fn (string $field): bool => $this->isSupplierFieldEmpty($field),
        ));
    }

    public function hasCompleteSupplierProfile(): bool
    {
        return $this->missingSupplierFields() === [];
    }

    private function isSupplierFieldEmpty(string $field): bool
    {
        $value = $this->getAttribute($field);

        if ($value === null) {
            return true;
        }

        return is_string($value) && trim($value) === '';
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasOne<TenantInterface, $this> */
    public function interface(): HasOne
    {
        return $this->hasOne(TenantInterface::class);
    }

    /** @return HasMany<TenantMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_memberships')
            ->withPivot(['is_active', 'joined_at'])
            ->withTimestamps();
    }
}
