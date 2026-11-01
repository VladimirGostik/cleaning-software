<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TaskRunStatusEnum: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case NeedsInput = 'needs_input';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case CouldNotFinish = 'could_not_finish';
    case Blocked = 'blocked';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Queued, self::Running];
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::active());
    }
}
