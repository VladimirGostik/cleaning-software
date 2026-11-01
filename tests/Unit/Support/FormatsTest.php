<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

final class FormatsTest extends TestCase
{
    public function test_format_date_has_no_zero_padding(): void
    {
        $this->assertSame('1. 11. 2026', format_date(Carbon::parse('2026-11-01')));
        $this->assertSame('25. 12. 2026', format_date(Carbon::parse('2026-12-25')));
    }

    public function test_format_date_returns_dash_for_null(): void
    {
        $this->assertSame('—', format_date(null));
    }

    public function test_format_phone_groups_slovak_numbers(): void
    {
        $this->assertSame('+421 900 000 000', format_phone('+421900000000'));
        $this->assertSame('+421 900 000 000', format_phone('  +421900000000 '));
    }

    public function test_format_phone_leaves_other_numbers_unchanged(): void
    {
        $this->assertSame('0900 000 000', format_phone('0900 000 000'));
        $this->assertSame('+420123456789', format_phone('+420123456789'));
        $this->assertSame('+421 900 000 000', format_phone('+421 900 000 000'));
        $this->assertSame('', format_phone('   '));
    }

    public function test_format_phone_returns_null_for_null(): void
    {
        $this->assertNull(format_phone(null));
    }
}
