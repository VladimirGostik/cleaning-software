<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;
use App\Enums\TaskRunStatusEnum;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/** Fixed text per status: whatever the AI wrote stays in the task thread, where its audience is enforced. */
#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(60)]
final class TaskRunFinishedNotification extends TaskNotification
{
    public function __construct(?string $tenantId, string $taskId, string $taskTitle, public readonly TaskRunStatusEnum $status)
    {
        parent::__construct($tenantId, $taskId, $taskTitle);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::TaskAiRun;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_task_ai_run_title', ['title' => $this->taskTitle]);
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_task_ai_run_body_'.$this->status->value);
    }

    /** @return array<string, mixed> */
    protected function meta(): array
    {
        return [...parent::meta(), 'status' => $this->status->value];
    }
}
