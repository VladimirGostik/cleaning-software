<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Data\Ai\AssistantActionData;

final readonly class TaskRunRequest
{
    /**
     * @param  list<array{author: string, kind: string, at: string, body: string}>  $comments
     * @param  list<AssistantActionData>  $priorActions
     */
    public function __construct(
        public string $runId,
        public string $taskId,
        public string $companyId,
        public bool $isGlobal,
        public string $title,
        public ?string $description,
        public string $priority,
        public ?string $dueAt,
        public ?string $subjectLabel,
        public int $attachmentsCount,
        public array $comments,
        public int $omittedComments,
        public array $priorActions,
    ) {}
}
