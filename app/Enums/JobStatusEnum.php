<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum JobStatusEnum: string
{
    case Planned = 'planned';
    case Unassigned = 'unassigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Unapproved = 'unapproved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('app.job_status.' . $this->value);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Unassigned => [self::Planned, self::Cancelled],
            self::Planned => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed, self::Unapproved],
            self::Completed, self::Unapproved, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isEditable(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Unapproved, self::Cancelled], true);
    }
}
