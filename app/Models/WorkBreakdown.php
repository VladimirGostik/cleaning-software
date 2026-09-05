<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use Database\Factories\WorkBreakdownFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $cleaning_object_id
 * @property string|null $contract_id
 * @property string|null $source_quote_id
 * @property string $name
 * @property bool $is_active
 * @property Collection<int, WorkBreakdownTask> $tasks
 * @property Contract|null $contract
 * @property CleaningObject|null $cleaningObject
 * @property Quote|null $sourceQuote
 */
#[Fillable([
    'tenant_id',
    'cleaning_object_id',
    'contract_id',
    'source_quote_id',
    'name',
    'is_active',
])]
final class WorkBreakdown extends Model
{
    /** @use HasFactory<WorkBreakdownFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'is_active', 'contract_id', 'cleaning_object_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<CleaningObject, $this>
     */
    public function cleaningObject(): BelongsTo
    {
        return $this->belongsTo(CleaningObject::class, 'cleaning_object_id');
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function sourceQuote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'source_quote_id');
    }

    /**
     * @return HasMany<WorkBreakdownTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkBreakdownTask::class)->orderBy('position');
    }

    /**
     * @return HasMany<ScheduledJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(ScheduledJob::class, 'work_breakdown_id');
    }
}
