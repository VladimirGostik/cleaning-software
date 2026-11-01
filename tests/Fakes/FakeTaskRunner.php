<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\RunsTasks;
use App\Enums\TaskRunOutcomeEnum;
use App\Exceptions\AiUnavailableException;
use App\Models\User;
use App\Services\Ai\TaskRunRequest;
use App\Services\Ai\TaskRunResult;
use Closure;
use RuntimeException;

final class FakeTaskRunner implements RunsTasks
{
    public bool $available = true;

    public bool $throws = false;

    public ?TaskRunResult $result = null;

    /** @var (Closure(TaskRunRequest, User): void)|null */
    public ?Closure $whileRunning = null;

    /** @var list<TaskRunRequest> */
    public array $requests = [];

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function run(TaskRunRequest $request, User $starter): TaskRunResult
    {
        $this->requests[] = $request;

        if ($this->throws) {
            throw AiUnavailableException::failed(new RuntimeException('timeout'));
        }

        if ($this->whileRunning !== null) {
            ($this->whileRunning)($request, $starter);
        }

        return $this->result ?? new TaskRunResult(TaskRunOutcomeEnum::Done, 'All done.');
    }
}
