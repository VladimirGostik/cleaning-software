<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationTypeEnum;
use App\Enums\PermissionEnum;
use App\Events\ContractExpiring as ContractExpiringEvent;
use App\Models\Contract;
use App\Notifications\ContractExpiring as ContractExpiringNotification;
use App\Scopes\TenantScope;
use App\Services\NotificationRecipientResolver;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class NotifyContractExpiring
{
    public function __construct(private readonly NotificationRecipientResolver $recipients) {}

    public function handle(ContractExpiringEvent $event): void
    {
        $users = $this->recipients->usersWithPermission($event->tenantId, PermissionEnum::ViewContracts);

        if ($users->isEmpty()) {
            return;
        }

        $contract = Contract::withoutGlobalScope(TenantScope::class)->findOrFail($event->contractId);

        NotificationFacade::send($users, new ContractExpiringNotification(
            $event->tenantId,
            $event->contractId,
            $event->daysLeft,
            $contract->number ?? $contract->title,
        ));

        logger()->info('notification.dispatched', [
            'type' => NotificationTypeEnum::ContractExpiring->value,
            'tenant_id' => $event->tenantId,
            'contract_id' => $event->contractId,
            'recipients' => $users->count(),
        ]);
    }
}
