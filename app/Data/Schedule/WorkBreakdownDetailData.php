<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Enums\ContractStatusEnum;
use App\Models\WorkBreakdown;
use App\Models\WorkBreakdownTask;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class WorkBreakdownDetailData extends Data
{
    /** @param WorkBreakdownTaskData[] $tasks */
    public function __construct(
        public readonly string $id,
        public readonly string $cleaning_object_id,
        public readonly string $name,
        public readonly bool $is_active,
        public readonly ?string $contract_id,
        public readonly ?string $contract_title,
        public readonly ?ContractStatusEnum $contract_status,
        public readonly ?string $source_quote_id,
        #[DataCollectionOf(WorkBreakdownTaskData::class)]
        public readonly array $tasks,
    ) {}

    /** Expects eager `tasks`, `contract:id,title,status`. */
    public static function fromModel(WorkBreakdown $breakdown): self
    {
        return new self(
            id: $breakdown->id,
            cleaning_object_id: $breakdown->cleaning_object_id,
            name: $breakdown->name,
            is_active: $breakdown->is_active,
            contract_id: $breakdown->contract_id,
            contract_title: $breakdown->contract?->title,
            contract_status: $breakdown->contract?->status,
            source_quote_id: $breakdown->source_quote_id,
            tasks: $breakdown->tasks->map(fn (WorkBreakdownTask $task) => WorkBreakdownTaskData::fromModel($task))->all(),
        );
    }
}
