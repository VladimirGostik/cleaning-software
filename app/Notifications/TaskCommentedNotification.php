<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(60)]
final class TaskCommentedNotification extends TaskNotification
{
    public function __construct(?string $tenantId, string $taskId, string $taskTitle, public readonly string $authorName)
    {
        parent::__construct($tenantId, $taskId, $taskTitle);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::TaskCommented;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_task_commented_title', ['title' => $this->taskTitle]);
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_task_commented_body', ['name' => $this->authorName]);
    }
}
