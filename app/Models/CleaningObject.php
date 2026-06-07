<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\ObjectTypeEnum;
use Database\Factories\CleaningObjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $driver = DB::getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $operator): void {
            $q->where('name', $operator, '%' . $term . '%')
                ->orWhere('street', $operator, '%' . $term . '%')
                ->orWhere('city', $operator, '%' . $term . '%');
        });
    }
}
