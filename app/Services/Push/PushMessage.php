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
    ) {}

    /** @return array<string, mixed> */
    public function toFcmPayload(): array
    {
        return [
            'notification' => ['title' => $this->title, 'body' => $this->body],
            'data' => ['route' => $this->route, 'notification_id' => $this->notificationId, 'tenant_id' => $this->tenantId],
            'apns' => ['payload' => ['aps' => ['sound' => 'default', 'badge' => $this->badge]]],
            'android' => ['priority' => 'high'],
        ];
    }
}
