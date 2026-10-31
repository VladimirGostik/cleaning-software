<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationTypeEnum;
use App\Events\TaskAssigned;
use App\Models\TenantMembership;
use App\Notifications\TaskAssignedNotification;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class NotifyTaskAssigned
{
    public function __construct(private readonly TaskService $tasks) {}

    public function handle(TaskAssigned $event): void
    {
        try {
            $task = $this->tasks->findForSystem($event->taskId);
        } catch (ModelNotFoundException) {
            return;
        }

        if ($task->assigned_membership_id === null) {
            return;
        }

        $task->loadMissing(['creator:id,name', 'tenant:id,name']);

        $assignee = TenantMembership::query()
            ->with('user')
            ->find($task->assigned_membership_id)?->user;

        if ($assignee === null || ! $assignee->is_active || ! $assignee->memberships()->where('tenant_id', $task->tenant_id)->where('is_active', true)->exists()) {
            return;
        }

        $assignee->notify(new TaskAssignedNotification(
            $task->tenant_id,
            $task->id,
            $task->title,
            $task->creator->name ?? '',
            $task->tenant->name ?? '',
        ));

        logger()->info('notification.dispatched', [
            'type' => NotificationTypeEnum::TaskAssigned->value,
            'tenant_id' => $task->tenant_id,
            'task_id' => $task->id,
        ]);
    }
}
