<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuids;
use App\Enums\TaskRunStatusEnum;
use App\Scopes\TaskCommentScope;
use Database\Factories\TaskRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $task_id
 * @property string $started_by_user_id
 * @property TaskRunStatusEnum $status
 * @property bool $closed_task
 * @property list<array{type: string, label: string, url: string|null}> $actions
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Tenant $tenant
 * @property Task $task
 * @property User $starter
 * @property TaskComment|null $comment
 */
#[Fillable(['tenant_id', 'task_id', 'started_by_user_id', 'status', 'closed_task', 'actions', 'started_at', 'finished_at'])]
final class TaskRun extends Model
{
    /** @use HasFactory<TaskRunFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TaskRunStatusEnum::class,
            'closed_task' => 'boolean',
            'actions' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    /** @return HasOne<TaskComment, $this> */
    public function comment(): HasOne
    {
        return $this->hasOne(TaskComment::class)->withoutGlobalScope(TaskCommentScope::class);
    }

    /**
     * Queued runs are measured from creation (a worker may be busy), running ones from the claim: a run cannot legitimately run past the job timeout.
     *
     * @param  Builder<TaskRun>  $query
     * @return Builder<TaskRun>
     */
    public function scopeStale(Builder $query): Builder
    {
        return $query->where(fn (Builder $stale) => $stale
            ->where(fn (Builder $queued) => $queued->where('status', TaskRunStatusEnum::Queued->value)->where('created_at', '<', self::queuedCutoff()))
            ->orWhere(fn (Builder $running) => $running->where('status', TaskRunStatusEnum::Running->value)->where('started_at', '<', self::runningCutoff())));
    }

    public function isStale(): bool
    {
        return match ($this->status) {
            TaskRunStatusEnum::Queued => $this->created_at->lt(self::queuedCutoff()),
            TaskRunStatusEnum::Running => ($this->started_at ?? $this->created_at)->lt(self::runningCutoff()),
            default => false,
        };
    }

    private static function queuedCutoff(): Carbon
    {
        return now()->subMinutes(config()->integer('ai.task_run.stale_minutes', 15));
    }

    private static function runningCutoff(): Carbon
    {
        return now()->subSeconds(config()->integer('ai.task_run.running_stale_seconds', 120));
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
