<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TaskActivityFieldEnum: string
{
    case Title = 'title';
    case Description = 'description';
    case Status = 'status';
    case Priority = 'priority';
    case DueAt = 'due_at';
    case RemindAt = 'remind_at';
    case Assignee = 'assignee';
    case Subject = 'subject';

    /** @return list<string> */
    public function columns(): array
    {
        return match ($this) {
            self::Assignee => ['assigned_membership_id'],
            self::Subject => ['taskable_type', 'taskable_id'],
            default => [$this->value],
        };
    }

    public function showsValues(): bool
    {
        return $this !== self::Description && $this !== self::Subject;
    }
}
