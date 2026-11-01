<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Data\Tasks\TaskDraftData;
use App\Exceptions\AiUnavailableException;
use App\Services\Ai\TaskExtractionContext;

interface ExtractsTaskDraft
{
    /** @throws AiUnavailableException */
    public function extract(string $text, TaskExtractionContext $context): TaskDraftData;
}
