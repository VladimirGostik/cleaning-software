<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;

final class InvoiceOverdue extends BaseTenantNotification
{
    public function __construct(
        string $tenantId,
        public readonly string $invoiceId,
    ) {
        parent::__construct($tenantId);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::InvoiceOverdue;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_type.invoice.overdue.title');
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_type.invoice.overdue.body');
    }

    protected function url(object $notifiable): ?string
    {
        return route('invoices.show', $this->invoiceId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function meta(): array
    {
        return ['invoice_id' => $this->invoiceId];
    }
}
