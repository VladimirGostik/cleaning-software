<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\ClientTypeEnum;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $tenant_id
 * @property ClientTypeEnum $type
 * @property string $name
 * @property string|null $ico
 * @property string|null $dic
 * @property string|null $vat_number
 * @property bool $is_vat_payer
 * @property string|null $street
 * @property string|null $city
 * @property string|null $postal_code
 * @property string $country
 * @property string|null $note
 * @property Carbon $created_at
 * @property int|null $contacts_count
 * @property int|null $objects_count
 * @property Collection<int, ClientContact> $contacts
 * @property Collection<int, CleaningObject> $objects
 */
#[Fillable([
    'type', 'name', 'ico', 'dic', 'vat_number', 'is_vat_payer',
    'street', 'city', 'postal_code', 'country', 'note',
])]
final class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ClientTypeEnum::class,
            'is_vat_payer' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'type', 'name', 'ico', 'dic', 'vat_number', 'is_vat_payer',
                'street', 'city', 'postal_code', 'country',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return HasMany<ClientContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /**
     * @return HasOne<ClientContact, $this>
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }

    /**
     * `CleaningObject` uses `SoftDeletes`, so this relation (and any `withCount('objects')`)
     * excludes trashed objects via the model's own global scope — a soft-deleted object (D1:
     * cascade from a destroyed client) will not appear here or be counted. `is_active` is a
     * separate, orthogonal switch (direct user deactivation) and does NOT filter this relation;
     * an inactive-but-not-trashed object still shows up. See `CleaningObject::isVisibleTo` for
     * the tenant/actor visibility axis, and `CleaningObject::client()` for the `withTrashed()`
     * note on the inverse relation.
     *
     * @return HasMany<CleaningObject, $this>
     */
    public function objects(): HasMany
    {
        return $this->hasMany(CleaningObject::class);
    }
}
