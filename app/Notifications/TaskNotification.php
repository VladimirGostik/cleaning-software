<?php

declare(strict_types=1);

namespace App\Notifications;

/** Carries a snapshot of the task text so delivery needs no task lookup (queued, no auth user, no tenant). */
abstract class TaskNotification extends BaseTenantNotification
{
    public function __construct(?string $tenantId, public readonly string $taskId, public readonly string $taskTitle)
    {
        parent::__construct($tenantId);
    }

    protected function url(object $notifiable): string
    {
        return route('tasks.index', ['task' => $this->taskId]);
    }

    /** @return array<string, mixed> */
    protected function meta(): array
    {
        return ['task_id' => $this->taskId];
    }
}
