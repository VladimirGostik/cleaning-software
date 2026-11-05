<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RecurringBilledMonthEnum: string
{
    case None = 'none';
    case Current = 'current';
    case Previous = 'previous';

    public const string TOKEN = '{mesiac/rok}';

    public function label(): string
    {
        return __('app.recurring_billed_month_'.$this->value);
    }

    public function billedMonth(CarbonInterface $runDate): ?CarbonImmutable
    {
        $first = CarbonImmutable::parse($runDate->toDateString())->startOfMonth();

        return match ($this) {
            self::None => null,
            self::Current => $first,
            self::Previous => $first->subMonthNoOverflow(),
        };
    }

    public function runDate(CarbonInterface $billedMonth, int $dayOfMonth): CarbonImmutable
    {
        $first = CarbonImmutable::parse($billedMonth->toDateString())->startOfMonth();

        if ($this === self::Previous) {
            $first = $first->addMonthNoOverflow();
        }

        return $first->setDay(min($dayOfMonth, $first->daysInMonth));
    }

    public static function formatMonth(CarbonInterface $month): string
    {
        return $month->format('n/Y');
    }
}
