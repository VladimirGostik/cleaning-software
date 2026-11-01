<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationTypeEnum;
use App\Events\TaskRunFinished;
use App\Models\User;
use App\Notifications\TaskRunFinishedNotification;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class NotifyTaskRunFinished
{
    public function __construct(private readonly TaskService $tasks) {}

    public function handle(TaskRunFinished $event): void
    {
        try {
            $task = $this->tasks->findForSystem($event->taskId);
        } catch (ModelNotFoundException) {
            return;
        }

        $starter = User::query()->find($event->starterUserId);

        if ($starter === null || ! $starter->is_active) {
            return;
        }

        if (! $task->isGlobal() && ! $starter->hasActiveMembership($task->tenant_id)) {
            return;
        }

        $starter->notify(new TaskRunFinishedNotification($task->tenant_id, $task->id, $task->title, $event->status));

        logger()->info('notification.dispatched', [
            'type' => NotificationTypeEnum::TaskAiRun->value,
            'tenant_id' => $task->tenant_id,
            'task_id' => $task->id,
        ]);
    }
}
