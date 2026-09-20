<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\CurrencyEnum;
use PHPUnit\Framework\TestCase;

final class CurrencyEnumTest extends TestCase
{
    public function test_symbol_per_case(): void
    {
        $this->assertSame('€', CurrencyEnum::EUR->symbol());
        $this->assertSame('Kč', CurrencyEnum::CZK->symbol());
        $this->assertSame('$', CurrencyEnum::USD->symbol());
    }

    public function test_format_defaults_to_two_decimals_with_nbsp_thousands_separator(): void
    {
        $this->assertSame("2\u{00A0}011,88\u{00A0}€", CurrencyEnum::EUR->format(2011.88));
    }

    public function test_format_grows_past_two_decimals_when_amount_needs_it(): void
    {
        $this->assertSame('18,125'."\u{00A0}€", CurrencyEnum::EUR->format(18.125, 3));
    }

    public function test_format_never_drops_below_two_decimals_even_when_max_decimals_allows_more(): void
    {
        $this->assertSame('18,10'."\u{00A0}€", CurrencyEnum::EUR->format(18.10, 3));
    }

    public function test_format_uses_currency_specific_suffix(): void
    {
        $this->assertSame('100,00'."\u{00A0}Kč", CurrencyEnum::CZK->format(100.0));
        $this->assertSame('100,00'."\u{00A0}\$", CurrencyEnum::USD->format(100.0));
    }
}
