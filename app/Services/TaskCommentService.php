<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tasks\TaskCommentStoreData;
use App\Enums\TaskCommentAuthorEnum;
use App\Events\TaskCommented;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskRun;
use App\Models\User;
use App\Scopes\TaskCommentScope;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class TaskCommentService
{
    public function __construct(private DatabaseManager $db, private TaskRunLookup $lookup) {}

    public function add(Task $task, TaskCommentStoreData $data, User $actor): TaskComment
    {
        $count = $task->comments()->count();

        if ($count >= TaskComment::MAX_PER_TASK) {
            throw ValidationException::withMessages(['body' => [__('app.task_comment_limit_reached', ['max' => TaskComment::MAX_PER_TASK])]]);
        }

        if ($data->answers_comment_id !== null) {
            $this->assertAnswerable($task, $data->answers_comment_id, $actor, $count);
        }

        try {
            return $this->db->transaction(function () use ($task, $data, $actor): TaskComment {
                $comment = $this->create($task, $actor, TaskCommentAuthorEnum::User, $data->body, null, $data->answers_comment_id);

                if (! $task->isGlobal()) {
                    TaskCommented::dispatch($comment->id);
                }

                return $comment;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['answers_comment_id' => [__('app.task_comment_answer_invalid')]]);
        }
    }

    public function addForRun(Task $task, TaskRun $run, string $body): TaskComment
    {
        $run->loadMissing('starter:id,name');

        return $this->create($task, $run->starter, TaskCommentAuthorEnum::Ai, $body, $run->id, null);
    }

    /** Queue and listener contexts have no bound tenant or user, so the comment scope would hide every row. */
    public function findForSystem(string $id): TaskComment
    {
        return TaskComment::query()->withoutGlobalScope(TaskCommentScope::class)->findOrFail($id);
    }

    public function accountableUserId(string $commentId): ?string
    {
        /** @var string|null */
        return TaskComment::query()->withoutGlobalScope(TaskCommentScope::class)->withTrashed()->whereKey($commentId)->value('user_id');
    }

    public function delete(Task $task, string $commentId, User $actor): void
    {
        $comment = $task->comments()
            ->visibleInThread($actor, Gate::forUser($actor)->allows('seesAllAiOutput', $task))
            ->findOrFail($commentId);

        Gate::forUser($actor)->authorize('deleteComment', [$task, $comment]);

        $this->db->transaction(fn () => $comment->delete());
    }

    private function assertAnswerable(Task $task, string $questionId, User $actor, int $commentCount): void
    {
        if (! $task->isOpen()) {
            throw ValidationException::withMessages(['status' => [__('app.task_not_open_for_answer')]]);
        }

        $question = $this->lookup->question($task, $this->lookup->visibleTo($this->lookup->latest($task), $task, $actor), $commentCount);

        if ($question === null || $question->comment_id !== $questionId || ! $question->can_answer) {
            throw ValidationException::withMessages(['answers_comment_id' => [__('app.task_comment_answer_invalid')]]);
        }
    }

    private function create(Task $task, User $author, TaskCommentAuthorEnum $kind, string $body, ?string $runId, ?string $answersCommentId): TaskComment
    {
        return TaskComment::create([
            'tenant_id' => $task->tenant_id,
            'task_id' => $task->id,
            'user_id' => $author->id,
            'author_kind' => $kind,
            'task_run_id' => $runId,
            'answers_comment_id' => $answersCommentId,
            'body' => $body,
        ]);
    }
}
