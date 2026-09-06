<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\BaseTenantNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification as IlluminateNotification;
use InvalidArgumentException;

/** Stamps the recipient's row with the tenant the notification belongs to. */
final class TenantDatabaseChannel extends DatabaseChannel
{
    /**
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, IlluminateNotification $notification): array
    {
        if (! $notification instanceof BaseTenantNotification) {
            throw new InvalidArgumentException('TenantDatabaseChannel requires a BaseTenantNotification instance.');
        }

        /** @var array<string, mixed> $payload */
        $payload = parent::buildPayload($notifiable, $notification);

        return [
            ...$payload,
            'tenant_id' => $notification->tenantId,
        ];
    }
}
