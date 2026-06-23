<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WorkBreakdown;
use App\Scopes\TenantScope;
use App\Services\JobService;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(120)]
final class GenerateScheduledJobsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $workBreakdownId,
    ) {}

    public function uniqueId(): string
    {
        return $this->workBreakdownId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(JobService $jobService, PermissionRegistrar $permissionRegistrar): void
    {
        /** @var WorkBreakdown $breakdown */
        $breakdown = WorkBreakdown::withoutGlobalScope(TenantScope::class)
            ->with(['tasks', 'cleaningObject', 'contract'])
            ->findOrFail($this->workBreakdownId);

        // Idempotency guard — skip if breakdown is no longer active.
        if (! $breakdown->is_active) {
            return;
        }

        // Bind tenant context so TenantScope and global scopes work inside JobService.
        app()->instance('current_tenant_id', $breakdown->tenant_id);
        $permissionRegistrar->setPermissionsTeamId($breakdown->tenant_id);

        $horizonDays = (int) config('scheduling.horizon_days', 30);

        $periodStart = Carbon::today();
        $periodEnd = Carbon::today()->addDays($horizonDays);

        // Respect contract validity window.
        if ($breakdown->contract !== null) {
            $contractEnd = $breakdown->contract->end_date;
            if ($contractEnd !== null && $contractEnd->lt($periodEnd)) {
                $periodEnd = $contractEnd;
            }
        }

        if ($periodEnd->lt($periodStart)) {
            return;
        }

        $period = CarbonPeriod::create($periodStart, '1 day', $periodEnd);

        $count = $jobService->generateForBreakdown($breakdown, $period);

        Log::info('schedule.jobs.generated', [
            'work_breakdown_id' => $this->workBreakdownId,
            'tenant_id' => $breakdown->tenant_id,
            'generated' => $count,
            'horizon_days' => $horizonDays,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('schedule.jobs.generate.failed', [
            'work_breakdown_id' => $this->workBreakdownId,
            'exception' => $e?->getMessage(),
            'trace' => $e?->getTraceAsString(),
        ]);
    }
}
