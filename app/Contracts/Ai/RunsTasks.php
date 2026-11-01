<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Exceptions\AiUnavailableException;
use App\Models\User;
use App\Services\Ai\TaskRunRequest;
use App\Services\Ai\TaskRunResult;

interface RunsTasks
{
    public function isAvailable(): bool;

    /** @throws AiUnavailableException */
    public function run(TaskRunRequest $request, User $starter): TaskRunResult;
}
