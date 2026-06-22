<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;

final class ContractExpired extends BaseTenantNotification
{
    public function __construct(
        string $tenantId,
        public readonly string $contractId,
    ) {
        parent::__construct($tenantId);
    }

    public function notificationType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::ContractExpired;
    }

    protected function title(object $notifiable): string
    {
        return __('app.notification_type.contract.expired.title');
    }

    protected function body(object $notifiable): string
    {
        return __('app.notification_type.contract.expired.body');
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
        return ['contract_id' => $this->contractId];
    }
}
