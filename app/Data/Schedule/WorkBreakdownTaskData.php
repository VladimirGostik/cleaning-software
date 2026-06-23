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
        public string $id,
        public string $name,
        public ?string $description,
        public TaskFrequencyEnum $frequency,
        public int $position,
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
