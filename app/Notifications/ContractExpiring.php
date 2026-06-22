<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;

final class ContractExpiring extends BaseTenantNotification
{
    public function __construct(
        string $tenantId,
        public readonly string $contractId,
        public readonly int $daysRemaining,
    ) {
        parent::__construct($tenantId);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::ContractExpiring;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_type.contract.expiring.title', ['days' => $this->daysRemaining]);
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_type.contract.expiring.body', ['days' => $this->daysRemaining]);
    }

    protected function url(object $notifiable): ?string
    {
        return route('contracts.show', $this->contractId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function meta(): array
    {
        return [
            'contract_id' => $this->contractId,
            'days_remaining' => $this->daysRemaining,
        ];
    }
}
