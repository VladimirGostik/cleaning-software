<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;

final class QuoteExpired extends BaseTenantNotification
{
    public function __construct(
        string $tenantId,
        public readonly string $quoteId,
    ) {
        parent::__construct($tenantId);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::QuoteExpired;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_type.quote.expired.title');
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_type.quote.expired.body');
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
        return ['quote_id' => $this->quoteId];
    }
}
