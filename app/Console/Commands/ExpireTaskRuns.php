<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TaskRunService;
use Illuminate\Console\Command;

final class ExpireTaskRuns extends Command
{
    protected $signature = 'cleanup:task-runs';

    protected $description = 'Fail AI task runs that stayed queued or running past the stale limit';

    public function handle(TaskRunService $runs): int
    {
        $this->info('Failed stale task runs: '.$runs->failStale().'.');

        return self::SUCCESS;
    }
}
