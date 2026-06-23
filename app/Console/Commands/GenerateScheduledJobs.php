<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateScheduledJobsJob;
use App\Models\WorkBreakdown;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;

final class GenerateScheduledJobs extends Command
{
    protected $signature = 'app:generate-scheduled-jobs';

    protected $description = 'Dispatch jobs to generate scheduled cleaning jobs from active work breakdowns';

    public function handle(): int
    {
        WorkBreakdown::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereHas('contract', fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('status', 'active'))
            ->lazyById(500)
            ->each(fn (WorkBreakdown $breakdown) => GenerateScheduledJobsJob::dispatch($breakdown->id));

        $this->info('Scheduled job generation dispatched.');

        return self::SUCCESS;
    }
}
