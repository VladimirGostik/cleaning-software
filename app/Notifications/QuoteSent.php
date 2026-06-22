<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;

final class QuoteSent extends BaseTenantNotification
{
    public function __construct(
        string $tenantId,
        public readonly string $quoteId,
    ) {
        parent::__construct($tenantId);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::QuoteSent;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_type.quote.sent.title');
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_type.quote.sent.body');
    }

    protected function url(object $notifiable): ?string
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
