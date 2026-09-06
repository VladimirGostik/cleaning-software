<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationTypeEnum;
use App\Enums\PermissionEnum;
use App\Events\QuoteExpiring as QuoteExpiringEvent;
use App\Models\Quote;
use App\Notifications\QuoteExpiring as QuoteExpiringNotification;
use App\Scopes\TenantScope;
use App\Services\NotificationRecipientResolver;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class NotifyQuoteExpiring
{
    public function __construct(private readonly NotificationRecipientResolver $recipients) {}

    public function handle(QuoteExpiringEvent $event): void
    {
        $users = $this->recipients->usersWithPermission($event->tenantId, PermissionEnum::ViewQuotes);

        if ($users->isEmpty()) {
            return;
        }

        $quote = Quote::withoutGlobalScope(TenantScope::class)->findOrFail($event->quoteId);

        NotificationFacade::send($users, new QuoteExpiringNotification(
            $event->tenantId,
            $event->quoteId,
            $event->daysLeft,
            $quote->number ?? __('app.quote_no_number'),
        ));

        logger()->info('notification.dispatched', [
            'type' => NotificationTypeEnum::QuoteExpiring->value,
            'tenant_id' => $event->tenantId,
            'quote_id' => $event->quoteId,
            'recipients' => $users->count(),
        ]);
    }
}
