<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use Database\Factories\WorkBreakdownFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property string $cleaning_object_id
 * @property string|null $contract_id
 * @property string|null $source_quote_id
 * @property string $name
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property CleaningObject|null $cleaningObject
 * @property Contract|null $contract
 * @property Quote|null $sourceQuote
 * @property Collection<int, WorkBreakdownTask> $tasks
 * @property Collection<int, ScheduledJob> $jobs
 */
#[Fillable(['tenant_id', 'cleaning_object_id', 'contract_id', 'source_quote_id', 'name', 'is_active'])]
final class WorkBreakdown extends Model
{
    /** @use HasFactory<WorkBreakdownFactory> */
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /** @return array<string, string> */
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

    /** @return BelongsTo<CleaningObject, $this> */
    public function cleaningObject(): BelongsTo
    {
        return $this->belongsTo(CleaningObject::class)->withTrashed();
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withTrashed();
    }

    /** @return BelongsTo<Quote, $this> */
    public function sourceQuote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'source_quote_id')->withTrashed();
    }

    /** @return HasMany<WorkBreakdownTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkBreakdownTask::class)->orderBy('position');
    }

    /** @return HasMany<ScheduledJob, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(ScheduledJob::class, 'work_breakdown_id');
    }
}
