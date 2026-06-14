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
        return __('app.recurring_invoices.frequency.' . $this->value);
    }

    public function nextRunDate(Carbon $from, int $dayOfMonth): Carbon
    {
        $next = $from->copy()->addMonths($this->monthsInterval());
        $maxDay = min($dayOfMonth, $next->daysInMonth);
        $next->setDay($maxDay);

        return $next->startOfDay();
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
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
