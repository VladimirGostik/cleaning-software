<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Events\ContractExpired;
use App\Events\ContractExpiring;
use App\Models\Contract;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;

final class CheckContractExpiry extends Command
{
    protected $signature = 'app:check-contract-expiry';

    protected $description = 'Expire fixed-term active contracts past their end date and notify about upcoming expiry';

    public function handle(): int
    {
        Contract::withoutGlobalScope(TenantScope::class)
            ->where('status', ContractStatusEnum::Active->value)
            ->where('term_type', ContractTermTypeEnum::Fixed->value)
            ->whereDate('end_date', '<', today())
            ->lazyById(500)
            ->each(function (Contract $contract): void {
                $contract->update(['status' => ContractStatusEnum::Expired]);

                logger()->info('contract.expired', ['contract_id' => $contract->id, 'tenant_id' => $contract->tenant_id]);

                ContractExpired::dispatch($contract->tenant_id, $contract->id);
            });

        /** @var list<mixed> $noticeDays */
        $noticeDays = (array) config('contracts.expiring_notice_days', []);

        foreach ($noticeDays as $rawDays) {
            $days = is_numeric($rawDays) ? (int) $rawDays : 0;

            Contract::withoutGlobalScope(TenantScope::class)
                ->where('status', ContractStatusEnum::Active->value)
                ->where('term_type', ContractTermTypeEnum::Fixed->value)
                ->whereDate('end_date', today()->addDays($days))
                ->lazyById(500)
                ->each(function (Contract $contract) use ($days): void {
                    logger()->info('contract.expiry_approaching', [
                        'contract_id' => $contract->id,
                        'tenant_id' => $contract->tenant_id,
                        'days_remaining' => $days,
                    ]);

                    ContractExpiring::dispatch($contract->tenant_id, $contract->id, $days);
                });
        }

        $this->info('Contract expiry check complete.');

        return self::SUCCESS;
    }
}
