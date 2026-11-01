<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Data\Ai\AssistantActionData;
use App\Enums\ActionChannelEnum;
use App\Enums\TaskRunOutcomeEnum;
use App\Enums\TaskRunStatusEnum;
use App\Events\TaskRunFinished;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskRun;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Services\TaskCommentService;
use App\Services\TaskRunLookup;
use App\Services\TaskService;
use Closure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class TaskRunFinisher
{
    public function __construct(
        private DatabaseManager $db,
        private TaskService $tasks,
        private TaskCommentService $comments,
        private ToolAudit $audit,
        private TaskRunLookup $lookup,
    ) {}

    public function finish(TaskRun $run, Task $task, User $starter, TaskRunResult $result): void
    {
        $locale = $starter->preferredLocale();

        $this->db->transaction(fn () => $this->inLocale($locale, function () use ($run, $task, $starter, $result): void {
            $locked = $this->lockForEnding($run->id);

            if ($locked === null) {
                return;
            }

            $status = $this->statusFor($result, $task);
            $closed = $status === TaskRunStatusEnum::Done && $this->closeTask($locked, $task, $starter);
            $modelText = in_array($status, [TaskRunStatusEnum::Blocked, TaskRunStatusEnum::Failed], true) ? '' : $result->message;

            $this->conclude($locked, $task, $status, $this->body($modelText, $this->noticeFor($status, $result, $closed)), $result->actions, $closed);
        }));
    }

    public function endWithNotice(string $runId, TaskRunStatusEnum $status, string $noticeKey, bool $onlyIfStale = false): void
    {
        $this->db->transaction(function () use ($runId, $status, $noticeKey, $onlyIfStale): void {
            $run = $this->lockForEnding($runId, $onlyIfStale);

            if ($run === null) {
                return;
            }

            $run->loadMissing('starter');
            $task = $this->tasks->findForSystem($run->task_id, withTrashed: true);

            $this->inLocale($run->starter->preferredLocale(), fn () => $this->conclude($run, $task, $status, __('app.'.$noticeKey), [], false));
        });
    }

    private function inLocale(string $locale, Closure $callback): void
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            $callback();
        } finally {
            app()->setLocale($previous);
        }
    }

    private function statusFor(TaskRunResult $result, Task $task): TaskRunStatusEnum
    {
        return match (true) {
            $result->restricted => TaskRunStatusEnum::Blocked,
            $result->providerFailed => TaskRunStatusEnum::Failed,
            $result->outcome === null, $result->outcome === TaskRunOutcomeEnum::Cannot => TaskRunStatusEnum::CouldNotFinish,
            $result->outcome === TaskRunOutcomeEnum::Question => TaskRunStatusEnum::NeedsInput,
            $result->refusedWrites > 0, $result->cutShort => TaskRunStatusEnum::CouldNotFinish,
            $this->hasPendingActions($task) => TaskRunStatusEnum::AwaitingConfirmation,
            default => TaskRunStatusEnum::Done,
        };
    }

    private function noticeFor(TaskRunStatusEnum $status, TaskRunResult $result, bool $closed): string
    {
        return match (true) {
            $status === TaskRunStatusEnum::Blocked => 'task_run_notice_blocked',
            $status === TaskRunStatusEnum::Failed => 'task_run_notice_failed',
            $status === TaskRunStatusEnum::AwaitingConfirmation => 'task_run_notice_awaiting',
            $status === TaskRunStatusEnum::Done => $closed ? 'task_run_notice_closed' : 'task_run_notice_not_closed',
            $result->outcome === TaskRunOutcomeEnum::Done => 'task_run_notice_writes_refused',
            $result->outcome === null && $result->message === '' => 'task_run_notice_no_report',
            default => '',
        };
    }

    private function body(string $modelText, string $noticeKey): string
    {
        $text = Str::limit(trim($modelText), TaskComment::MAX_BODY_CHARS - 400, '');
        $notice = $noticeKey === '' ? '' : __('app.'.$noticeKey);

        return trim($text === '' && $notice === '' ? __('app.task_run_notice_empty') : $text."\n\n".$notice);
    }

    private function closeTask(TaskRun $run, Task $task, User $starter): bool
    {
        try {
            $fresh = $this->tasks->findForSystem($task->id);
        } catch (ModelNotFoundException) {
            return false;
        }

        if (! $fresh->isOpen() || ! Gate::forUser($starter)->allows('update', $fresh)) {
            return false;
        }

        try {
            $this->tasks->complete($fresh);
        } catch (ValidationException) {
            return false;
        }

        $this->audit->record($starter, 'task_run_close', $run->tenant_id, ActionChannelEnum::TaskRun, ['run_id' => $run->id], 'ok');

        return true;
    }

    /** @param  list<AssistantActionData>  $actions */
    private function conclude(TaskRun $run, Task $task, TaskRunStatusEnum $status, string $body, array $actions, bool $closedTask): void
    {
        $run->update([
            'status' => $status,
            'closed_task' => $closedTask,
            'actions' => array_map(static fn (AssistantActionData $action): array => $action->toArray(), $actions),
            'finished_at' => now(),
        ]);

        $this->comments->addForRun($task, $run, $body);

        TaskRunFinished::dispatch($task->id, $run->started_by_user_id, $status);
    }

    private function lockForEnding(string $runId, bool $onlyIfStale = false): ?TaskRun
    {
        return TaskRun::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereKey($runId)
            ->whereIn('status', TaskRunStatusEnum::activeValues())
            ->when($onlyIfStale, fn (Builder $query) => $query->stale())
            ->lockForUpdate()
            ->first();
    }

    private function hasPendingActions(Task $task): bool
    {
        return $this->lookup->livePendingActions($task)->exists();
    }
}
