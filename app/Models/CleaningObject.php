<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\ObjectTypeEnum;
use App\Enums\PermissionEnum;
use Database\Factories\CleaningObjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $client_id
 * @property ObjectTypeEnum $type
 * @property string $name
 * @property string|null $street
 * @property string|null $city
 * @property string|null $postal_code
 * @property string $country
 * @property string|null $access_code
 * @property string|null $key_box_code
 * @property int|null $key_count
 * @property string|null $special_instructions
 * @property string|null $area_sqm
 * @property int|null $floor
 * @property bool $is_active
 * @property string|null $gps_lat
 * @property string|null $gps_lng
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property Client|null $client
 * @property Collection<int, WorkBreakdown> $workBreakdowns
 * @property Collection<int, ScheduledJob> $jobs
 *
 * D1 override (phase 3 plan): two lifecycle switches on this model — `is_active` for direct
 * user deactivation, `deleted_at` for the soft-delete cascade fired by `ClientService::delete()`
 * when the parent client is destroyed. Readers filtering "still usable" objects must check
 * `is_active = true` AND not trashed; `client()` uses `withTrashed()` so a deactivated object
 * whose client was later deleted still resolves `client_name`.
 */
#[Table('objects')]
#[Fillable([
    'client_id', 'type', 'name', 'street', 'city', 'postal_code', 'country',
    'access_code', 'key_box_code', 'key_count', 'special_instructions',
    'area_sqm', 'floor', 'is_active', 'gps_lat', 'gps_lng',
])]
final class CleaningObject extends Model
{
    /** @use HasFactory<CleaningObjectFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ObjectTypeEnum::class,
            'is_active' => 'boolean',
            'area_sqm' => 'decimal:2',
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'key_count' => 'integer',
            'floor' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'client_id', 'type', 'name', 'street', 'city', 'postal_code',
                'country', 'access_code', 'key_box_code', 'key_count',
                'special_instructions', 'area_sqm', 'floor', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * `withTrashed()` — an object of a soft-deleted client (D1 cascade) must still resolve
     * `client_name` in lists/detail. Re-activating such an object requires re-pointing it at
     * a live client (`ObjectUpsertData::rules()` keeps `whereNull('deleted_at')`).
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /** @return HasMany<WorkBreakdown, $this> */
    public function workBreakdowns(): HasMany
    {
        return $this->hasMany(WorkBreakdown::class, 'cleaning_object_id');
    }

    /** @return HasMany<ScheduledJob, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(ScheduledJob::class, 'cleaning_object_id');
    }

    /**
     * Scopes the query to objects the actor may see. Fail-closed: with `ViewAllObjects` the
     * actor sees the tenant's objects; without it, only objects reachable through ANY job
     * (any status/date) assigned to the actor's active membership.
     *
     * @param  Builder<CleaningObject>  $query
     * @return Builder<CleaningObject>
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->can(PermissionEnum::ViewAllObjects->value)) {
            return $query;
        }

        $membershipId = $actor->activeMembershipId();

        if ($membershipId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('jobs', fn (Builder $q) => $q->where('assigned_membership_id', $membershipId));
    }

    public function isVisibleTo(User $actor): bool
    {
        if ($actor->can(PermissionEnum::ViewAllObjects->value)) {
            return true;
        }

        return self::query()->visibleTo($actor)->whereKey($this->id)->exists();
    }
}
