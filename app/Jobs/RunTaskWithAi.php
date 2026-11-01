<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TaskRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/** Never retried: a second attempt would repeat the writes the first one already made. */
#[Tries(1)]
#[Timeout(self::TIMEOUT_SECONDS)]
#[FailOnTimeout]
final class RunTaskWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Managed queues on Laravel Cloud cut jobs at 90 s; the turn and step budgets in config/ai.php must fit under this. */
    public const int TIMEOUT_SECONDS = 85;

    public function __construct(public readonly string $runId) {}

    public function handle(TaskRunService $runs): void
    {
        $runs->execute($this->runId);
    }

    public function failed(?Throwable $exception): void
    {
        app(TaskRunService::class)->markFailed($this->runId);
    }
}
