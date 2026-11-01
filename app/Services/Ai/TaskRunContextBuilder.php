<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Data\Ai\AssistantActionData;
use App\Data\Tasks\TaskSubjectData;
use App\Enums\PermissionEnum;
use App\Enums\TaskRunStatusEnum;
use App\Models\Task;
use App\Models\TaskRun;
use App\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final readonly class TaskRunContextBuilder
{
    private const int PRIOR_RUNS = 10;

    private const int MAX_PRIOR_ACTIONS = 30;

    public function __construct(private TaskPolicy $policy) {}

    public function build(Task $task, TaskRun $run, User $starter): TaskRunRequest
    {
        $seesAllAi = $this->policy->seesAllAiOutput($starter, $task);
        [$comments, $omitted] = $this->comments($task, $starter, $seesAllAi);
        $task->load(['taskable' => fn (MorphTo $morph) => $morph->constrain(Task::subjectConstraints())]);
        $subject = TaskSubjectData::fromTask($task);

        return new TaskRunRequest(
            runId: $run->id,
            taskId: $task->id,
            companyId: $run->tenant_id,
            isGlobal: $task->isGlobal(),
            title: $task->title,
            description: $task->description,
            priority: $task->priority->value,
            dueAt: $task->due_at?->toIso8601String(),
            subjectLabel: $subject === null ? null : $subject->type.': '.$subject->label.' (id '.$subject->id.')',
            attachmentsCount: $task->media()->where('collection_name', Task::ATTACHMENTS_COLLECTION)->count(),
            comments: $comments,
            omittedComments: $omitted,
            priorActions: $this->priorActions($task, $run, $starter),
        );
    }

    /** @return array{0: list<array{author: string, kind: string, at: string, body: string}>, 1: int} */
    private function comments(Task $task, User $starter, bool $seesAllAi): array
    {
        $visible = $task->comments()->visibleInThread($starter, $seesAllAi);
        $total = (clone $visible)->count();
        $maxChars = config()->integer('ai.task_run.context_chars', 12000);

        $rows = $visible->with('author:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(config()->integer('ai.task_run.context_comments', 30))
            ->get();

        $included = [];
        $chars = 0;

        foreach ($rows as $comment) {
            $chars += mb_strlen($comment->body);

            if ($chars > $maxChars && $included !== []) {
                break;
            }

            $included[] = [
                'author' => $comment->author->name,
                'kind' => $comment->author_kind->value,
                'at' => $comment->created_at->toIso8601String(),
                'body' => $comment->body,
            ];
        }

        return [array_reverse($included), $total - count($included)];
    }

    /** @return list<AssistantActionData> */
    private function priorActions(Task $task, TaskRun $current, User $starter): array
    {
        $holdsViewAll = $starter->can(PermissionEnum::ViewAllTasks->value);

        $runs = $task->aiRuns()
            ->whereKeyNot($current->id)
            ->whereNotIn('status', TaskRunStatusEnum::activeValues())
            ->orderByDesc('created_at')
            ->limit(self::PRIOR_RUNS)
            ->get()
            ->filter(fn (TaskRun $run): bool => TaskPolicy::aiOutputAudienceIncludes($task->isGlobal(), $task->created_by_user_id === $starter->id, $holdsViewAll, $run->started_by_user_id === $starter->id));

        $actions = [];

        foreach ($runs as $run) {
            foreach ($run->actions as $action) {
                $actions[] = AssistantActionData::from($action);
            }
        }

        return array_slice($actions, 0, self::MAX_PRIOR_ACTIONS);
    }
}
