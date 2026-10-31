<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationTypeEnum;
use App\Events\TaskDue;
use App\Models\Task;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\TaskDueNotification;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class NotifyTaskDue
{
    public function __construct(private readonly TaskService $tasks) {}

    public function handle(TaskDue $event): void
    {
        try {
            $task = $this->tasks->findForSystem($event->taskId);
        } catch (ModelNotFoundException) {
            return;
        }

        if (! $task->isOpen()) {
            return;
        }

        $recipient = $this->recipient($task);

        if ($recipient === null || ! $recipient->is_active || ! $this->isActiveMember($recipient, $task)) {
            return;
        }

        $recipient->notify(new TaskDueNotification($task->tenant_id, $task->id, $task->title, $task->due_at?->toIso8601String()));

        logger()->info('notification.dispatched', [
            'type' => NotificationTypeEnum::TaskDue->value,
            'tenant_id' => $task->tenant_id,
            'task_id' => $task->id,
        ]);
    }

    private function isActiveMember(User $user, Task $task): bool
    {
        return $task->tenant_id === null
            || $user->memberships()->where('tenant_id', $task->tenant_id)->where('is_active', true)->exists();
    }

    private function recipient(Task $task): ?User
    {
        if ($task->assigned_membership_id === null) {
            return $task->creator()->first();
        }

        return TenantMembership::query()
            ->with('user')
            ->find($task->assigned_membership_id)?->user;
    }
}
