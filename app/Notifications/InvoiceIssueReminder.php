<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(60)]
final class InvoiceIssueReminder extends BaseTenantNotification
{
    public function __construct(
        string $tenantId,
        public readonly string $month,
        public readonly string $tenantName,
    ) {
        parent::__construct($tenantId);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::InvoiceIssueReminder;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_invoice_issue_reminder_title', ['month' => $this->month]);
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_invoice_issue_reminder_body', ['month' => $this->month, 'tenant' => $this->tenantName]);
    }

    protected function url(object $notifiable): string
    {
        return route('invoices.index', ['filter' => ['status' => 'draft']]);
    }

    /** @return array<string, mixed> */
    protected function meta(): array
    {
        return ['month' => CarbonImmutable::createFromFormat('!n/Y', $this->month)?->format('Y-m')];
    }
}
