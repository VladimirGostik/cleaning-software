<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Contracts\SendsPush;
use App\Models\User;
use App\Notifications\BaseTenantNotification;
use App\Services\DeviceTokenService;
use App\Services\NotificationService;
use App\Services\Push\PushMessage;
use App\Services\Push\PushRoute;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class FcmChannel
{
    public function __construct(
        private SendsPush $sender,
        private DeviceTokenService $devices,
        private NotificationService $notifications,
        private TenantPermissionSnapshot $snapshot,
    ) {}

    public function send(object $notifiable, BaseTenantNotification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        $tokens = $this->devices->tokensFor($notifiable);

        if ($tokens === [] || ! $this->snapshot->hasMobileAccess($notifiable, $notification->tenantId)) {
            return;
        }

        $payload = $notification->toArray($notifiable);
        $title = $payload['title'] ?? '';
        $body = $payload['body'] ?? '';

        $message = new PushMessage(
            title: is_string($title) ? $title : '',
            body: is_string($body) ? $body : '',
            route: PushRoute::fromUrl($payload['url'] ?? null),
            tenantId: $notification->tenantId ?? '',
            notificationId: $notification->id,
            badge: $this->notifications->badgeFor($notifiable, $notification->id),
        );

        // Swallowed on purpose: a retry after a partial multicast failure would re-send to the tokens that already got it.
        try {
            $this->devices->removeTokens($this->sender->send($message, $tokens));
        } catch (Throwable $e) {
            Log::error('push.send.failed', [
                'type' => $notification->notificationType()->value,
                'user_id' => $notifiable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
