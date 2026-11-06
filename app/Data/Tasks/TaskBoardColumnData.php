<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Enums\TaskStatusEnum;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskBoardColumnData extends Data
{
    /** @param  list<TaskListItemData>  $tasks */
    public function __construct(
        public readonly TaskStatusEnum $status,
        public readonly int $total,
        #[DataCollectionOf(TaskListItemData::class)]
        public readonly array $tasks,
        public readonly bool $has_more,
    ) {}
}
