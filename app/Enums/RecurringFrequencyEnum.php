<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Carbon;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RecurringFrequencyEnum: string
{
    case Monthly = 'monthly';
    case EveryTwoMonths = 'every_2_months';
    case Quarterly = 'quarterly';
    case SemiAnnually = 'semi_annually';
    case Annually = 'annually';

    public function monthsInterval(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::EveryTwoMonths => 2,
            self::Quarterly => 3,
            self::SemiAnnually => 6,
            self::Annually => 12,
        };
    }

    public function label(): string
    {
        return __('app.recurring_frequency_'.$this->value);
    }

    public function nextRunDate(Carbon $from, int $dayOfMonth): Carbon
    {
        $next = $from->copy()->addMonths($this->monthsInterval());
        $maxDay = min($dayOfMonth, $next->daysInMonth);
        $next->setDay($maxDay);

        return $next->startOfDay();
    }
}
