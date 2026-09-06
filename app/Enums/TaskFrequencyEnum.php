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
        return __('app.task_frequency_'.$this->value);
    }

    /** Days between recurrences, or null for a one-time task. */
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

    public function isRecurring(): bool
    {
        return $this !== self::OneTime;
    }
}
