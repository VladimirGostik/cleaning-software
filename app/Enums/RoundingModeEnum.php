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
        return __('app.rounding_mode.' . $this->value);
    }

    public function round(float $amount): float
    {
        return match ($this) {
            self::None => $amount,
            self::Document => round($amount, 0),
            self::Cash005 => round($amount / 0.05) * 0.05,
        };
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
}
