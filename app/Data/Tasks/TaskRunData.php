<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Data\Ai\AssistantActionData;
use App\Enums\TaskRunStatusEnum;
use App\Models\TaskRun;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskRunData extends Data
{
    /** @param  list<AssistantActionData>  $actions */
    public function __construct(
        public readonly string $id,
        public readonly TaskRunStatusEnum $status,
        public readonly bool $is_active,
        public readonly bool $closed_task,
        public readonly string $started_by_name,
        public readonly string $tenant_id,
        public readonly string $tenant_name,
        public readonly ?string $finished_at,
        #[DataCollectionOf(AssistantActionData::class)]
        public readonly array $actions,
    ) {}

    /** Expects `starter` and `tenant` loaded. */
    public static function fromModel(TaskRun $run): self
    {
        return new self(
            id: $run->id,
            status: $run->status,
            is_active: $run->isActive(),
            closed_task: $run->closed_task,
            started_by_name: $run->starter->name,
            tenant_id: $run->tenant_id,
            tenant_name: $run->tenant->name,
            finished_at: $run->finished_at?->toIso8601String(),
            actions: array_map(AssistantActionData::from(...), $run->actions),
        );
    }
}
