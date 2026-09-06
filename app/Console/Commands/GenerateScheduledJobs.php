<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ContractStatusEnum;
use App\Jobs\GenerateScheduledJobsJob;
use App\Models\WorkBreakdown;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

final class GenerateScheduledJobs extends Command
{
    protected $signature = 'app:generate-scheduled-jobs';

    protected $description = 'Dispatch job generation for every active work breakdown with an active contract';

    public function handle(): int
    {
        $count = 0;

        WorkBreakdown::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->whereHas('contract', fn (Builder $q) => $q->withoutGlobalScope(TenantScope::class)->where('status', ContractStatusEnum::Active->value))
            ->lazyById(500)
            ->each(function (WorkBreakdown $breakdown) use (&$count): void {
                GenerateScheduledJobsJob::dispatch($breakdown->id);
                $count++;
            });

        $this->info("Dispatched job generation for {$count} work breakdown(s).");

        return self::SUCCESS;
    }
}
