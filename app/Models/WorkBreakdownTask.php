<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\TaskFrequencyEnum;
use Database\Factories\WorkBreakdownTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'work_breakdown_id',
    'name',
    'description',
    'frequency',
    'position',
])]
final class WorkBreakdownTask extends Model
{
    /** @use HasFactory<WorkBreakdownTaskFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => TaskFrequencyEnum::class,
            'position' => 'integer',
        ];
    }

    public function workBreakdown(): BelongsTo
    {
        return $this->belongsTo(WorkBreakdown::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ScheduledJob::class, 'work_breakdown_task_id');
    }
}
