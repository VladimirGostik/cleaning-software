<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tasks\TaskQuestionData;
use App\Enums\PendingActionStatusEnum;
use App\Enums\TaskRunStatusEnum;
use App\Models\PendingAction;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskRun;
use App\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Database\Eloquent\Builder;

final readonly class TaskRunLookup
{
    public function __construct(private TaskPolicy $policy) {}

    public function latest(Task $task, bool $withParties = false): ?TaskRun
    {
        $task->loadMissing($withParties ? ['latestAiRun.starter:id,name', 'latestAiRun.tenant:id,name'] : 'latestAiRun');

        return $task->latestAiRun;
    }

    public function visibleTo(?TaskRun $run, Task $task, User $viewer): ?TaskRun
    {
        return $run !== null && $this->policy->viewAiOutput($viewer, $task, $run->started_by_user_id) ? $run : null;
    }

    public function question(Task $task, ?TaskRun $visibleRun, int $commentCount): ?TaskQuestionData
    {
        if ($visibleRun === null || $visibleRun->status !== TaskRunStatusEnum::NeedsInput || $visibleRun->comment === null) {
            return null;
        }

        $questionId = $visibleRun->comment->id;
        $answered = $task->comments()->where('answers_comment_id', $questionId)->exists();

        return new TaskQuestionData(
            comment_id: $questionId,
            answered: $answered,
            can_answer: ! $answered && $task->isActive() && $commentCount < TaskComment::MAX_PER_TASK,
        );
    }

    /** @return Builder<PendingAction> Unconfirmed, unexpired actions prepared by any run of the task. */
    public function livePendingActions(Task $task): Builder
    {
        return PendingAction::query()
            ->where('status', PendingActionStatusEnum::Pending->value)
            ->where('expires_at', '>', now())
            ->whereIn('task_run_id', $task->aiRuns()->select('task_runs.id'));
    }
}
