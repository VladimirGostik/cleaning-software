<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Models\WorkBreakdown;
use App\Models\WorkBreakdownTask;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class WorkBreakdownDetailData extends Data
{
    /**
     * @param  WorkBreakdownTaskData[]  $tasks
     */
    public function __construct(
        public string $id,
        public string $cleaning_object_id,
        public string $name,
        public bool $is_active,
        public ?string $contract_id,
        public ?string $source_quote_id,
        public array $tasks,
    ) {}

    public static function fromModel(WorkBreakdown $breakdown): self
    {
        return new self(
            id: $breakdown->id,
            cleaning_object_id: $breakdown->cleaning_object_id,
            name: $breakdown->name,
            is_active: (bool) $breakdown->is_active,
            contract_id: $breakdown->contract_id,
            source_quote_id: $breakdown->source_quote_id,
            tasks: $breakdown->tasks
                ->map(fn (WorkBreakdownTask $task) => WorkBreakdownTaskData::fromModel($task))
                ->values()
                ->all(),
        );
    }
}
