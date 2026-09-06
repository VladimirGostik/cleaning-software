<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ContractStatusEnum;
use App\Models\WorkBreakdown;
use App\Scopes\TenantScope;
use App\Services\JobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

    public function handle(JobService $jobs, DatabaseManager $db, PermissionRegistrar $registrar): void
    {
        /** @var WorkBreakdown $breakdown */
        $breakdown = WorkBreakdown::withoutGlobalScope(TenantScope::class)
            ->with(['tasks', 'cleaningObject', 'contract'])
            ->findOrFail($this->workBreakdownId);

        if (! $breakdown->is_active) {
            return;
        }

        if ($breakdown->contract?->status !== ContractStatusEnum::Active) {
            return;
        }

        app()->instance('current_tenant_id', $breakdown->tenant_id);
        $registrar->setPermissionsTeamId($breakdown->tenant_id);

        $horizonDays = config('scheduling.horizon_days', 30);
        $horizonDays = is_numeric($horizonDays) ? (int) $horizonDays : 30;

        $start = today();
        $end = today()->addDays($horizonDays);

        if ($breakdown->contract->end_date !== null && $breakdown->contract->end_date->lt($end)) {
            $end = $breakdown->contract->end_date;
        }

        $period = $start->toPeriod($end);

        $created = $db->transaction(fn () => $jobs->generateForBreakdown($breakdown, $period));

        Log::info('schedule.jobs.generated', [
            'work_breakdown_id' => $breakdown->id,
            'tenant_id' => $breakdown->tenant_id,
            'created' => $created,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('schedule.jobs.generate.failed', [
            'work_breakdown_id' => $this->workBreakdownId,
            'exception' => $e?->getMessage(),
        ]);
    }
}
