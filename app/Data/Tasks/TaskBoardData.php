<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskBoardData extends Data
{
    /** @param  list<TaskBoardColumnData>  $columns */
    public function __construct(
        #[DataCollectionOf(TaskBoardColumnData::class)]
        public readonly array $columns,
    ) {}
}
