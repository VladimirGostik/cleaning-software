<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TaskFrequencyEnum: string
{
    case OneTime = 'one_time';
    case Weekly1x = 'weekly_1x';
    case Weekly2x = 'weekly_2x';
    case Weekly3x = 'weekly_3x';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Bimonthly = 'bimonthly';
    case Seasonal = 'seasonal';

    public function label(): string
    {
        return __('app.task_frequency.' . $this->value);
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
     * Number of days between occurrences, or null for one-time (no repeat).
     */
    public function intervalDays(): ?int
    {
        return match ($this) {
            self::OneTime => null,
            self::Weekly1x => 7,
            self::Weekly2x => 3,
            self::Weekly3x => 2,
            self::Biweekly => 14,
            self::Monthly => 30,
            self::Bimonthly => 60,
            self::Seasonal => 90,
        };
    }

    /**
     * Approximate number of occurrences per week (for display/info purposes).
     */
    public function occurrencesPerWeek(): float
    {
        return match ($this) {
            self::OneTime => 0.0,
            self::Weekly1x => 1.0,
            self::Weekly2x => 2.0,
            self::Weekly3x => 3.0,
            self::Biweekly => 0.5,
            self::Monthly => 0.25,
            self::Bimonthly => 0.125,
            self::Seasonal => 0.08,
        };
    }

    public function isRecurring(): bool
    {
        return $this !== self::OneTime;
    }
}
