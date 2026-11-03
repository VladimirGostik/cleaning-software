<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum QuoteBillingPeriodEnum: string
{
    case OneOff = 'one_off';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return $this->translated('quote_period_');
    }

    public function adverb(): ?string
    {
        return $this->isRecurring() ? $this->translated('quote_period_adverb_') : null;
    }

    public function unitNoun(): ?string
    {
        return $this->isRecurring() ? $this->translated('quote_period_unit_') : null;
    }

    public function shortSuffix(): ?string
    {
        return $this->isRecurring() ? $this->translated('quote_period_suffix_') : null;
    }

    public function recurringFrequency(): ?RecurringFrequencyEnum
    {
        return match ($this) {
            self::OneOff => null,
            self::Monthly => RecurringFrequencyEnum::Monthly,
            self::Quarterly => RecurringFrequencyEnum::Quarterly,
            self::Yearly => RecurringFrequencyEnum::Annually,
        };
    }

    public function isRecurring(): bool
    {
        return $this !== self::OneOff;
    }

    private function translated(string $prefix): string
    {
        return __('app.'.$prefix.$this->value);
    }
}
