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
final class QuoteExpiring extends BaseTenantNotification
{
    public function __construct(
        string $tenantId,
        public readonly string $quoteId,
        public readonly int $daysLeft,
        public readonly string $quoteNumber,
    ) {
        parent::__construct($tenantId);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::QuoteExpiring;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_quote_expiring_title', ['number' => $this->quoteNumber, 'days' => $this->daysLeft]);
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_quote_expiring_body', ['number' => $this->quoteNumber, 'days' => $this->daysLeft]);
    }

    protected function url(object $notifiable): string
    {
        return route('quotes.show', $this->quoteId);
    }

    /** @return array<string, mixed> */
    protected function meta(): array
    {
        return ['quote_id' => $this->quoteId, 'days_left' => $this->daysLeft];
    }
}
