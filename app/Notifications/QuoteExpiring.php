<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;

final class QuoteExpiring extends BaseTenantNotification
{
    public function __construct(
        string $tenantId,
        public readonly string $quoteId,
        public readonly int $daysRemaining,
    ) {
        parent::__construct($tenantId);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::QuoteExpiring;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_type.quote.expiring.title', ['days' => $this->daysRemaining]);
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_type.quote.expiring.body', ['days' => $this->daysRemaining]);
    }

    protected function url(object $notifiable): string
    {
        return route('quotes.show', $this->quoteId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function meta(): array
    {
        return [
            'quote_id' => $this->quoteId,
            'days_remaining' => $this->daysRemaining,
        ];
    }
}
