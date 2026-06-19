<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * String values are UPPERCASE ISO 4217 codes — deliberate deviation from the project's lowercase-enum-value
 * convention. Rationale: the Pay-by-Square QR payload (setCurrency($invoice->currency->value)) and SK payment
 * tooling expect uppercase ISO 4217; uppercase values let ->value feed the QR/PDF boundary 1:1 with no
 * translation shim.
 */
#[TypeScript]
enum CurrencyEnum: string
{
    case EUR = 'EUR';
    case CZK = 'CZK';
    case USD = 'USD';

    public function label(): string
    {
        return __('app.currency.' . $this->value);
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
