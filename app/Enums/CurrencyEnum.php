<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Uppercase ISO 4217 values (deliberate deviation from lowercase-enum-value convention) —
 * feeds Pay-by-Square QR + SK payment tooling with no translation shim.
 */
#[TypeScript]
enum CurrencyEnum: string
{
    case EUR = 'EUR';
    case CZK = 'CZK';
    case USD = 'USD';

    public function label(): string
    {
        return __('app.currency_'.strtolower($this->value));
    }
}
