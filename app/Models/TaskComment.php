<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\TaskCommentAuthorEnum;
use App\Scopes\TaskCommentScope;
use Database\Factories\TaskCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $task_id
 * @property string $user_id
 * @property TaskCommentAuthorEnum $author_kind
 * @property string|null $task_run_id
 * @property string|null $answers_comment_id
 * @property string $body
 * @property Carbon|null $edited_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property User $author
 */
#[Fillable(['tenant_id', 'task_id', 'user_id', 'author_kind', 'task_run_id', 'answers_comment_id', 'body'])]
#[ScopedBy(TaskCommentScope::class)]
final class TaskComment extends Model
{
    /** @use HasFactory<TaskCommentFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public const int MAX_BODY_CHARS = 2000;

    public const int MAX_PER_TASK = 200;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['author_kind' => TaskCommentAuthorEnum::class, 'edited_at' => 'datetime'];
    }

    /** The body stays out of the log: AI text would otherwise reach audit viewers outside its audience. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['author_kind', 'task_run_id', 'answers_comment_id', 'edited_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = $this->tenant_id;
        $activity->keepNullTenant = $this->tenant_id === null;
    }

    /**
     * Human comments are public to the task's audience; AI comments only to their accountable user, unless the viewer sees all AI output.
     *
     * @param  Builder<TaskComment>  $query
     * @return Builder<TaskComment>
     */
    public function scopeVisibleInThread(Builder $query, User $actor, bool $seesAllAi): Builder
    {
        if ($seesAllAi) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($actor): void {
            $visible->where($this->qualifyColumn('author_kind'), TaskCommentAuthorEnum::User->value)
                ->orWhere($this->qualifyColumn('user_id'), $actor->id);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isByAi(): bool
    {
        return $this->author_kind === TaskCommentAuthorEnum::Ai;
    }
}
