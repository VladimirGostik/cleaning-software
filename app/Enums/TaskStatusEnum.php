<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TaskStatusEnum: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case NiceToHave = 'nice_to_have';
    case Done = 'done';

    public function label(): string
    {
        return __('app.task_status_'.$this->value);
    }

    public function isActive(): bool
    {
        return $this !== self::Done;
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return array_values(array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->isActive()),
        ));
    }

    /** @return list<self> */
    public static function boardOrder(): array
    {
        return [self::Open, self::InProgress, self::Waiting, self::NiceToHave, self::Done];
    }
}
