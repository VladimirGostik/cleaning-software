<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Enums\TaskReminderSlotEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskReminderSlotData extends Data
{
    public function __construct(
        public readonly TaskReminderSlotEnum $slot,
        public readonly int $hour,
    ) {}

    /** @return list<self> */
    public static function forAllSlots(): array
    {
        return array_map(
            static fn (TaskReminderSlotEnum $slot): self => new self($slot, $slot->hour()),
            TaskReminderSlotEnum::cases(),
        );
    }
}
