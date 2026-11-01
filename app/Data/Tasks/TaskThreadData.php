<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Data\Ai\PendingActionData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskThreadData extends Data
{
    /**
     * @param  list<TaskCommentData>  $comments
     * @param  list<PendingActionData>  $pending_actions
     * @param  list<TaskRunCompanyData>  $run_companies
     */
    public function __construct(
        #[DataCollectionOf(TaskCommentData::class)]
        public readonly array $comments,
        public readonly ?TaskRunData $latest_run,
        public readonly ?TaskQuestionData $question,
        #[DataCollectionOf(PendingActionData::class)]
        public readonly array $pending_actions,
        public readonly bool $ai_available,
        public readonly bool $can_comment,
        public readonly bool $can_start_run,
        #[DataCollectionOf(TaskRunCompanyData::class)]
        public readonly array $run_companies,
        public readonly int $max_body_chars,
    ) {}
}
