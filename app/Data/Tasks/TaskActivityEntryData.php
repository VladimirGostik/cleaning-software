<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Enums\TaskActivityKindEnum;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskActivityEntryData extends Data
{
    /** @param  list<TaskActivityChangeData>  $changes */
    public function __construct(
        public readonly string $id,
        public readonly TaskActivityKindEnum $kind,
        public readonly ?string $actor_name,
        public readonly string $occurred_at,
        #[DataCollectionOf(TaskActivityChangeData::class)]
        public readonly array $changes,
        public readonly ?string $attachment_name,
    ) {}
}
