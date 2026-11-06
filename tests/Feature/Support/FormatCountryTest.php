<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Tests\TestCase;

final class FormatCountryTest extends TestCase
{
    public function test_blank_values_return_null(): void
    {
        $this->assertNull(format_country(null));
        $this->assertNull(format_country('  '));
    }

    public function test_known_code_is_translated_in_the_current_locale(): void
    {
        app()->setLocale('sk');
        $this->assertSame('Slovensko', format_country(' sk '));

        app()->setLocale('en');
        $this->assertSame('Czechia', format_country('CZ'));
    }

    public function test_unknown_value_is_returned_trimmed(): void
    {
        $this->assertSame('XX', format_country(' XX '));
    }
}
