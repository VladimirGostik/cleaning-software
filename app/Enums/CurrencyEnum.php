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

    public function symbol(): string
    {
        return match ($this) {
            self::EUR => '€',
            self::CZK => 'Kč',
            self::USD => '$',
        };
    }

    /**
     * SK typographic convention: amount, NBSP, then the symbol as a suffix — same rule
     * for all three currencies (no locale-specific prefix variant needed here).
     * `$maxDecimals` never trims below 2 (currency floor); it only grows past 2 when
     * the amount actually carries a 3rd+ significant decimal (D1: unit price precision).
     */
    public function format(float $amount, int $maxDecimals = 2): string
    {
        $decimals = $this->significantDecimals($amount, $maxDecimals);

        return number_format($amount, $decimals, ',', "\u{00A0}")."\u{00A0}".$this->symbol();
    }

    private function significantDecimals(float $amount, int $maxDecimals): int
    {
        $rounded = number_format($amount, $maxDecimals, '.', '');
        $trimmed = rtrim(rtrim($rounded, '0'), '.');

        $dotPosition = strpos($trimmed, '.');
        $decimals = $dotPosition === false ? 0 : strlen($trimmed) - $dotPosition - 1;

        return max(2, min($maxDecimals, $decimals));
    }
}
