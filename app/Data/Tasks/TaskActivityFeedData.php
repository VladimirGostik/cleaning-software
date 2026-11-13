<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskActivityFeedData extends Data
{
    /** @param  list<TaskActivityEntryData>  $entries */
    public function __construct(
        #[DataCollectionOf(TaskActivityEntryData::class)]
        public readonly array $entries,
        public readonly bool $has_older,
    ) {}
}
