<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\ClientTypeEnum;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
 * @property string|null $country
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
     * @return HasMany<CleaningObject, $this>
     */
    public function objects(): HasMany
    {
        return $this->hasMany(CleaningObject::class);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $driver = DB::getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $operator): void {
            $q->where('name', $operator, '%' . $term . '%')
                ->orWhere('ico', $operator, '%' . $term . '%');
        });
    }
}
