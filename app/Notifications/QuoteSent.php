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
final class QuoteSent extends BaseTenantNotification
{
    public function __construct(
        string $tenantId,
        public readonly string $quoteId,
        public readonly string $quoteNumber,
    ) {
        parent::__construct($tenantId);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::QuoteSent;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_quote_sent_title', ['number' => $this->quoteNumber]);
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_quote_sent_body', ['number' => $this->quoteNumber]);
    }

    protected function url(object $notifiable): string
    {
        return route('quotes.show', $this->quoteId);
    }

    /** @return array<string, mixed> */
    protected function meta(): array
    {
        return ['quote_id' => $this->quoteId];
    }
}
