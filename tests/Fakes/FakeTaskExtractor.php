<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\ExtractsTaskDraft;
use App\Data\Tasks\TaskDraftData;
use App\Enums\TaskPriorityEnum;
use App\Exceptions\AiUnavailableException;
use App\Services\Ai\TaskExtractionContext;
use RuntimeException;

final class FakeTaskExtractor implements ExtractsTaskDraft
{
    public TaskDraftData $draft;

    public bool $unavailable = false;

    /** @var list<array{0: string, 1: TaskExtractionContext}> */
    public array $calls = [];

    public function __construct()
    {
        $this->draft = new TaskDraftData('Call the client', null, '2026-11-03T09:00:00+01:00', TaskPriorityEnum::Normal, null, null, null);
    }

    public function extract(string $text, TaskExtractionContext $context): TaskDraftData
    {
        $this->calls[] = [$text, $context];

        if ($this->unavailable) {
            throw AiUnavailableException::failed(new RuntimeException('timeout'));
        }

        return $this->draft;
    }
}
