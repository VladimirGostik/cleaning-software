<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationTypeEnum;
use App\Events\TaskCommented;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\TaskCommentedNotification;
use App\Services\TaskCommentService;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class NotifyTaskCommented
{
    public function __construct(private readonly TaskService $tasks, private readonly TaskCommentService $comments) {}

    public function handle(TaskCommented $event): void
    {
        try {
            $comment = $this->comments->findForSystem($event->commentId);
            $task = $this->tasks->findForSystem($comment->task_id);
        } catch (ModelNotFoundException) {
            return;
        }

        if ($task->isGlobal()) {
            return;
        }

        $comment->loadMissing('author:id,name');

        foreach ($this->recipients($comment, $task) as $recipient) {
            $recipient->notify(new TaskCommentedNotification($task->tenant_id, $task->id, $task->title, $comment->author->name));
        }

        logger()->info('notification.dispatched', [
            'type' => NotificationTypeEnum::TaskCommented->value,
            'tenant_id' => $task->tenant_id,
            'task_id' => $task->id,
        ]);
    }

    /** @return iterable<User> */
    private function recipients(TaskComment $comment, Task $task): iterable
    {
        $candidates = [$task->created_by_user_id];

        if ($task->assigned_membership_id !== null) {
            $assignee = TenantMembership::query()->whereKey($task->assigned_membership_id)->value('user_id');
            $candidates[] = is_string($assignee) ? $assignee : null;
        }

        if ($comment->answers_comment_id !== null) {
            $candidates[] = $this->comments->accountableUserId($comment->answers_comment_id);
        }

        $ids = array_values(array_diff(array_unique(array_filter($candidates)), [$comment->user_id]));

        return User::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('memberships', fn ($memberships) => $memberships->where('tenant_id', $task->tenant_id)->where('is_active', true))
            ->get();
    }
}
