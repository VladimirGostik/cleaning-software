<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function test_format_quantity_trims_trailing_zeros(): void
    {
        $this->assertSame('111', format_quantity(111.00));
        $this->assertSame('1,5', format_quantity(1.5));
        $this->assertSame('0,25', format_quantity(0.25));
    }

    public function test_format_quantity_keeps_nbsp_thousands_separator(): void
    {
        $this->assertSame("1\u{00A0}000", format_quantity(1000.0));
    }
}
