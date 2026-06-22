<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Contract;
use App\Notifications\ContractExpired;
use App\Notifications\ContractExpiring;
use App\Scopes\TenantScope;
use App\Services\NotificationRecipientResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

final class CheckContractExpiry extends Command
{
    protected $signature = 'app:check-contract-expiry';

    protected $description = 'Mark expired fixed-term contracts and log approaching expirations';

    public function handle(NotificationRecipientResolver $resolver): int
    {
        // Phase 1: Flip Active + fixed-term + end_date < today → Expired
        Contract::withoutGlobalScope(TenantScope::class)
            ->where('status', ContractStatusEnum::Active->value)
            ->where('term_type', ContractTermTypeEnum::Fixed->value)
            ->where('end_date', '<', now()->toDateString())
            ->lazyById(500)
            ->each(function (Contract $contract) use ($resolver): void {
                $contract->update(['status' => ContractStatusEnum::Expired]);

                logger()->info('contract.expired', [
                    'contract_id' => $contract->id,
                    'tenant_id' => $contract->tenant_id,
                    'end_date' => $contract->end_date->toDateString(),
                ]);

                $recipients = $resolver->usersWithPermission(
                    $contract->tenant_id,
                    PermissionEnum::ViewContracts,
                );

                Notification::send($recipients, new ContractExpired($contract->tenant_id, $contract->id));
            });

        // Phase 2: Log and notify contracts expiring in exactly 30, 14, or 7 days
        foreach ([30, 14, 7] as $days) {
            $target = now()->addDays($days)->toDateString();

            Contract::withoutGlobalScope(TenantScope::class)
                ->where('status', ContractStatusEnum::Active->value)
                ->where('term_type', ContractTermTypeEnum::Fixed->value)
                ->where('end_date', $target)
                ->lazyById(200)
                ->each(function (Contract $contract) use ($days, $resolver): void {
                    logger()->info('contract.expiry_approaching', [
                        'contract_id' => $contract->id,
                        'tenant_id' => $contract->tenant_id,
                        'end_date' => $contract->end_date->toDateString(),
                        'days_remaining' => $days,
                    ]);

                    $recipients = $resolver->usersWithPermission(
                        $contract->tenant_id,
                        PermissionEnum::ViewContracts,
                    );

                    Notification::send($recipients, new ContractExpiring($contract->tenant_id, $contract->id, $days));
                });
        }

        $this->info('Contract expiry check complete.');

        return self::SUCCESS;
    }
}
