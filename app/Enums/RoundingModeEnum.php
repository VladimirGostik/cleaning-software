<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RoundingModeEnum: string
{
    case None = 'none';
    case Document = 'document';
    case Cash005 = 'cash_005';

    public function label(): string
    {
        return __('app.rounding_mode_'.$this->value);
    }

    public function round(float $amount): float
    {
        return match ($this) {
            self::None => $amount,
            self::Document => round($amount),
            self::Cash005 => round($amount * 20) / 20,
        };
    }
}
