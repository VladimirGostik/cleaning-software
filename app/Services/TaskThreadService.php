<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Ai\RunsTasks;
use App\Data\Ai\PendingActionData;
use App\Data\Tasks\TaskCommentData;
use App\Data\Tasks\TaskRunCompanyData;
use App\Data\Tasks\TaskRunData;
use App\Data\Tasks\TaskRunStatusData;
use App\Data\Tasks\TaskThreadData;
use App\Enums\PermissionEnum;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskRun;
use App\Models\User;
use App\Policies\TaskPolicy;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class TaskThreadService
{
    public function __construct(
        private TaskPolicy $policy,
        private TaskRunLookup $lookup,
        private TaskRunService $runs,
        private RunsTasks $runner,
        private TenantPermissionSnapshot $snapshot,
    ) {}

    public function for(Task $task, User $viewer): TaskThreadData
    {
        $seesAllAi = $this->policy->seesAllAiOutput($viewer, $task);
        $latest = $this->expiring($this->lookup->latest($task, withParties: true));
        $visibleRun = $this->lookup->visibleTo($latest, $task, $viewer);
        $commentCount = $task->comments()->count();
        $runCompanies = $this->runCompanies($task, $viewer);

        return new TaskThreadData(
            comments: $this->comments($task, $viewer, $seesAllAi),
            latest_run: $visibleRun === null ? null : TaskRunData::fromModel($visibleRun),
            question: $this->lookup->question($task, $visibleRun, $commentCount),
            pending_actions: $this->pendingActions($task, $viewer),
            ai_available: $this->runner->isAvailable(),
            can_comment: $commentCount < TaskComment::MAX_PER_TASK && $this->policy->comment($viewer, $task),
            can_start_run: $task->isActive()
                && ! ($latest?->isActive() ?? false)
                && ! $this->lookup->livePendingActions($task)->where('user_id', $viewer->id)->exists()
                && $this->policy->startAiRun($viewer, $task, $task->isGlobal() ? ($runCompanies[0]->id ?? null) : null),
            run_companies: $runCompanies,
            max_body_chars: TaskComment::MAX_BODY_CHARS,
        );
    }

    public function runStatus(Task $task, User $viewer): TaskRunStatusData
    {
        $run = $this->lookup->visibleTo($this->expiring($this->lookup->latest($task)), $task, $viewer);

        return $run === null
            ? new TaskRunStatusData(null, null, false)
            : new TaskRunStatusData($run->id, $run->status, $run->isActive());
    }

    /** A failing expiry must never make the task unreadable. */
    private function expiring(?TaskRun $run): ?TaskRun
    {
        try {
            return $this->runs->expireIfStale($run);
        } catch (Throwable $exception) {
            Log::warning('task_run.expire_failed', ['exception' => $exception::class]);

            return $run;
        }
    }

    /** @return list<TaskCommentData> */
    private function comments(Task $task, User $viewer, bool $seesAllAi): array
    {
        $rows = $task->comments()
            ->visibleInThread($viewer, $seesAllAi)
            ->with('author:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(TaskComment::MAX_PER_TASK)
            ->get()
            ->reverse()
            ->values();

        $questionIds = $this->visibleQuestionIds($task, $viewer, $seesAllAi, $rows);

        return array_values($rows
            ->map(fn (TaskComment $comment): TaskCommentData => TaskCommentData::fromModel(
                $comment,
                $viewer->id,
                $this->policy->mayEditComment($viewer, $task, $comment, true),
                $this->policy->mayDeleteComment($viewer, $task, $comment, true),
                in_array($comment->answers_comment_id, $questionIds, true) ? $comment->answers_comment_id : null,
            ))
            ->all());
    }

    /**
     * @param  Collection<int, TaskComment>  $rows
     * @return list<string>
     */
    private function visibleQuestionIds(Task $task, User $viewer, bool $seesAllAi, Collection $rows): array
    {
        $ids = $rows->pluck('answers_comment_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        /** @var list<string> */
        return $task->comments()->visibleInThread($viewer, $seesAllAi)->whereIn('id', $ids)->pluck('id')->all();
    }

    /** @return list<PendingActionData> */
    private function pendingActions(Task $task, User $viewer): array
    {
        $actions = $this->lookup->livePendingActions($task)
            ->with('tenant:id,name')
            ->where('user_id', $viewer->id)
            ->whereIn('tenant_id', $viewer->memberships()->where('is_active', true)->select('tenant_id'))
            ->orderBy('expires_at')
            ->get();

        return array_values($actions->map(PendingActionData::fromModel(...))->all());
    }

    /** @return list<TaskRunCompanyData> */
    private function runCompanies(Task $task, User $viewer): array
    {
        if (! $task->isGlobal() || $task->created_by_user_id !== $viewer->id) {
            return [];
        }

        $companies = array_filter(
            $this->snapshot->forRequest($viewer),
            static fn (TenantVisibility $visibility): bool => in_array(PermissionEnum::RunTasksWithAi, $visibility->permissions, true),
        );

        return array_values(array_map(
            static fn (TenantVisibility $visibility): TaskRunCompanyData => new TaskRunCompanyData($visibility->tenant->id, $visibility->tenant->name),
            $companies,
        ));
    }
}
