<?php

declare(strict_types=1);

namespace App\Services\Push;

final readonly class PushMessage
{
    public function __construct(
        public string $title,
        public string $body,
        public string $route,
        public string $tenantId,
        public string $notificationId,
        public int $badge,
        public bool $badgeOnly = false,
    ) {}

    public static function badgeOnly(int $badge): self
    {
        return new self('', '', '', '', '', $badge, badgeOnly: true);
    }

    /** @return array<string, mixed> */
    public function toFcmPayload(): array
    {
        if ($this->badgeOnly) {
            return [
                'data' => ['kind' => 'badge'],
                'apns' => [
                    'headers' => ['apns-push-type' => 'alert', 'apns-priority' => '10'],
                    'payload' => ['aps' => ['badge' => $this->badge]],
                ],
                'android' => ['priority' => 'normal'],
            ];
        }

        return [
            'notification' => ['title' => $this->title, 'body' => $this->body],
            'data' => ['route' => $this->route, 'notification_id' => $this->notificationId, 'tenant_id' => $this->tenantId],
            'apns' => ['payload' => ['aps' => ['sound' => 'default', 'badge' => $this->badge]]],
            'android' => ['priority' => 'high'],
        ];
    }
}
