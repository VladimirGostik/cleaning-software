<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Enums\TaskFrequencyEnum;
use App\Models\WorkBreakdownTask;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class WorkBreakdownTaskData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly TaskFrequencyEnum $frequency,
        public readonly int $position,
    ) {}

    public static function fromModel(WorkBreakdownTask $task): self
    {
        return new self(
            id: $task->id,
            name: $task->name,
            description: $task->description,
            frequency: $task->frequency,
            position: $task->position,
        );
    }
}
