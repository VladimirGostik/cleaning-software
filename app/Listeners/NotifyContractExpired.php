<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationTypeEnum;
use App\Enums\PermissionEnum;
use App\Events\ContractExpired as ContractExpiredEvent;
use App\Models\Contract;
use App\Notifications\ContractExpired as ContractExpiredNotification;
use App\Scopes\TenantScope;
use App\Services\NotificationRecipientResolver;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class NotifyContractExpired
{
    public function __construct(private readonly NotificationRecipientResolver $recipients) {}

    public function handle(ContractExpiredEvent $event): void
    {
        $users = $this->recipients->usersWithPermission($event->tenantId, PermissionEnum::ViewContracts);

        if ($users->isEmpty()) {
            return;
        }

        $contract = Contract::withoutGlobalScope(TenantScope::class)->findOrFail($event->contractId);

        NotificationFacade::send($users, new ContractExpiredNotification(
            $event->tenantId,
            $event->contractId,
            $contract->number ?? $contract->title,
        ));

        logger()->info('notification.dispatched', [
            'type' => NotificationTypeEnum::ContractExpired->value,
            'tenant_id' => $event->tenantId,
            'contract_id' => $event->contractId,
            'recipients' => $users->count(),
        ]);
    }
}
