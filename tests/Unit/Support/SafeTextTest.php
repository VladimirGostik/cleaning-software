<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SafeText;
use PHPUnit\Framework\TestCase;

final class SafeTextTest extends TestCase
{
    public function test_clean_text_strips_formula_and_control_characters_and_truncates(): void
    {
        $this->assertSame('cmd|x', SafeText::clean('=cmd|x', 50));
        $this->assertSame('SUM(1)', SafeText::clean('+@-  SUM(1)', 50));
        $this->assertSame('a b', SafeText::clean("a\x00\t\r\nb", 50));
        $this->assertNull(SafeText::clean('=+-@', 50));
        $this->assertNull(SafeText::clean(null, 50));
        $this->assertNull(SafeText::clean("  \t ", 50));
        $this->assertSame('ččč', SafeText::clean('čččččč', 3));
    }

    public function test_manual_text_keeps_leading_symbols(): void
    {
        $this->assertSame('@Home s.r.o.', SafeText::clean('@Home s.r.o.', 50, false));
    }
}
