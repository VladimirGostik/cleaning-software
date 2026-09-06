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
final class ContractExpiring extends BaseTenantNotification
{
    public function __construct(
        string $tenantId,
        public readonly string $contractId,
        public readonly int $daysLeft,
        public readonly string $contractLabel,
    ) {
        parent::__construct($tenantId);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::ContractExpiring;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_contract_expiring_title', ['number' => $this->contractLabel, 'days' => $this->daysLeft]);
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_contract_expiring_body', ['number' => $this->contractLabel, 'days' => $this->daysLeft]);
    }

    protected function url(object $notifiable): string
    {
        return route('contracts.show', $this->contractId);
    }

    /** @return array<string, mixed> */
    protected function meta(): array
    {
        return ['contract_id' => $this->contractId, 'days_left' => $this->daysLeft];
    }
}
