<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\BaseTenantNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

final class TenantDatabaseChannel extends DatabaseChannel
{
    /**
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        /** @var BaseTenantNotification $notification */
        $base = parent::buildPayload($notifiable, $notification);

        return array_merge($base, ['tenant_id' => $notification->tenantId()]);
    }
}
