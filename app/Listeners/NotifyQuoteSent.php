<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationTypeEnum;
use App\Enums\PermissionEnum;
use App\Events\QuoteSent as QuoteSentEvent;
use App\Models\Quote;
use App\Notifications\QuoteSent as QuoteSentNotification;
use App\Scopes\TenantScope;
use App\Services\NotificationRecipientResolver;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class NotifyQuoteSent
{
    public function __construct(private readonly NotificationRecipientResolver $recipients) {}

    public function handle(QuoteSentEvent $event): void
    {
        $users = $this->recipients->usersWithPermission($event->tenantId, PermissionEnum::ViewQuotes);

        if ($users->isEmpty()) {
            return;
        }

        $quote = Quote::withoutGlobalScope(TenantScope::class)->findOrFail($event->quoteId);

        NotificationFacade::send($users, new QuoteSentNotification(
            $event->tenantId,
            $event->quoteId,
            $quote->number ?? __('app.quote_no_number'),
        ));

        logger()->info('notification.dispatched', [
            'type' => NotificationTypeEnum::QuoteSent->value,
            'tenant_id' => $event->tenantId,
            'quote_id' => $event->quoteId,
            'recipients' => $users->count(),
        ]);
    }
}
