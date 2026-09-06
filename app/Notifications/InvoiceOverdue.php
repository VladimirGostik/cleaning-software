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
final class InvoiceOverdue extends BaseTenantNotification
{
    public function __construct(
        string $tenantId,
        public readonly string $invoiceId,
        public readonly string $invoiceNumber,
    ) {
        parent::__construct($tenantId);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::InvoiceOverdue;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_invoice_overdue_title', ['number' => $this->invoiceNumber]);
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_invoice_overdue_body', ['number' => $this->invoiceNumber]);
    }

    protected function url(object $notifiable): string
    {
        return route('invoices.show', $this->invoiceId);
    }

    /** @return array<string, mixed> */
    protected function meta(): array
    {
        return ['invoice_id' => $this->invoiceId];
    }
}
