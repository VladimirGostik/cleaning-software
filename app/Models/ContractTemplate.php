<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\ContractCategoryEnum;
use Database\Factories\ContractTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property ContractCategoryEnum $category
 */
#[Fillable(['tenant_id', 'name', 'category', 'body', 'is_active'])]
final class ContractTemplate extends Model
{
    /** @use HasFactory<ContractTemplateFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ContractCategoryEnum::class,
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'category', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where('name', $operator, '%' . $term . '%');
    }
}
