<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Carbon;

#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(60)]
final class TaskDueNotification extends TaskNotification
{
    public function __construct(?string $tenantId, string $taskId, string $taskTitle, public readonly ?string $dueAt = null)
    {
        parent::__construct($tenantId, $taskId, $taskTitle);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::TaskDue;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_task_due_title', ['title' => $this->taskTitle]);
    }

    protected function body(object $notifiable): string
    {
        return $this->dueAt === null
            ? __('app.notification_task_due_body_no_date')
            : __('app.notification_task_due_body', ['due' => Carbon::parse($this->dueAt)->isoFormat('L LT')]);
    }
}
