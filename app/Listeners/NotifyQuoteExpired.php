<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationTypeEnum;
use App\Enums\PermissionEnum;
use App\Events\QuoteExpired as QuoteExpiredEvent;
use App\Models\Quote;
use App\Notifications\QuoteExpired as QuoteExpiredNotification;
use App\Scopes\TenantScope;
use App\Services\NotificationRecipientResolver;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class NotifyQuoteExpired
{
    public function __construct(private readonly NotificationRecipientResolver $recipients) {}

    public function handle(QuoteExpiredEvent $event): void
    {
        $users = $this->recipients->usersWithPermission($event->tenantId, PermissionEnum::ViewQuotes);

        if ($users->isEmpty()) {
            return;
        }

        $quote = Quote::withoutGlobalScope(TenantScope::class)->findOrFail($event->quoteId);

        NotificationFacade::send($users, new QuoteExpiredNotification(
            $event->tenantId,
            $event->quoteId,
            $quote->number ?? __('app.quote_no_number'),
        ));

        logger()->info('notification.dispatched', [
            'type' => NotificationTypeEnum::QuoteExpired->value,
            'tenant_id' => $event->tenantId,
            'quote_id' => $event->quoteId,
            'recipients' => $users->count(),
        ]);
    }
}
