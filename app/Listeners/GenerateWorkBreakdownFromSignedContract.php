<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ContractSigned;
use App\Jobs\GenerateScheduledJobsJob;
use App\Models\Contract;
use App\Scopes\TenantScope;
use App\Services\WorkBreakdownService;

/**
 * Sync — `ContractSigned` already fires after commit and generation is a handful of inserts
 * with no I/O; the user who signs expects the Rozpis prác on the same page load.
 */
final class GenerateWorkBreakdownFromSignedContract
{
    public function __construct(
        private readonly WorkBreakdownService $breakdowns,
    ) {}

    public function handle(ContractSigned $event): void
    {
        /** @var Contract $contract */
        $contract = Contract::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $event->tenantId)
            ->findOrFail($event->contractId);

        $breakdown = $this->breakdowns->generateFromContract($contract);

        if ($breakdown !== null && $breakdown->is_active) {
            GenerateScheduledJobsJob::dispatch($breakdown->id)->afterCommit();
        }

        logger()->info('schedule.breakdown.generated', [
            'tenant_id' => $event->tenantId,
            'contract_id' => $event->contractId,
            'work_breakdown_id' => $breakdown?->id,
            'tasks' => $breakdown?->tasks->count() ?? 0,
        ]);
    }
}
