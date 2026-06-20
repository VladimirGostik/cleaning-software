<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Models\Contract;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;

final class CheckContractExpiry extends Command
{
    protected $signature = 'app:check-contract-expiry';

    protected $description = 'Mark expired fixed-term contracts and log approaching expirations';

    public function handle(): int
    {
        // Phase 1: Flip Active + fixed-term + end_date < today → Expired
        Contract::withoutGlobalScope(TenantScope::class)
            ->where('status', ContractStatusEnum::Active->value)
            ->where('term_type', ContractTermTypeEnum::Fixed->value)
            ->where('end_date', '<', now()->toDateString())
            ->lazyById(500)
            ->each(function (Contract $contract): void {
                $contract->update(['status' => ContractStatusEnum::Expired]);

                logger()->info('contract.expired', [
                    'contract_id' => $contract->id,
                    'tenant_id' => $contract->tenant_id,
                    'end_date' => $contract->end_date->toDateString(),
                ]);
            });

        // Phase 2: Log contracts expiring in exactly 30, 14, or 7 days
        foreach ([30, 14, 7] as $days) {
            $target = now()->addDays($days)->toDateString();

            Contract::withoutGlobalScope(TenantScope::class)
                ->where('status', ContractStatusEnum::Active->value)
                ->where('term_type', ContractTermTypeEnum::Fixed->value)
                ->where('end_date', $target)
                ->lazyById(200)
                ->each(function (Contract $contract) use ($days): void {
                    logger()->info('contract.expiry_approaching', [
                        'contract_id' => $contract->id,
                        'tenant_id' => $contract->tenant_id,
                        'end_date' => $contract->end_date->toDateString(),
                        'days_remaining' => $days,
                        // TODO: dispatch notification when Notification module is built
                    ]);
                });
        }

        $this->info('Contract expiry check complete.');

        return self::SUCCESS;
    }
}
