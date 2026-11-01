<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\TaskRunStatusEnum;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class TaskRunFinished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $taskId,
        public readonly string $starterUserId,
        public readonly TaskRunStatusEnum $status,
    ) {}
}
