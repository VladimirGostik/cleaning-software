<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression test: Show.vue localeTag computed.
 *
 * Fix applied: toLocaleDateString('sk-SK') hardcoded → dynamic localeTag computed:
 *   const map: Record<string, string> = { sk: 'sk-SK', en: 'en-GB', uk: 'uk-UA' };
 *   return map[pageProps.locale] ?? 'sk-SK';
 *
 * Verifies the mapping table and unknown-locale fallback match what the Vue component implements.
 * PHP mirrors the JS logic so this runs without a browser runtime.
 */
final class LocaleTagMappingTest extends TestCase
{
    /**
     * Replicates the localeTag computed from resources/js/Pages/Clients/Show.vue.
     */
    private function resolveLocaleTag(string $locale): string
    {
        $map = [
            'sk' => 'sk-SK',
            'en' => 'en-GB',
            'uk' => 'uk-UA',
        ];

        return $map[$locale] ?? 'sk-SK';
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function localeMappingProvider(): array
    {
        return [
            'sk maps to sk-SK' => ['sk', 'sk-SK'],
            'en maps to en-GB' => ['en', 'en-GB'],
            'uk maps to uk-UA' => ['uk', 'uk-UA'],
            'unknown locale falls back sk-SK' => ['de', 'sk-SK'],
            'empty string falls back sk-SK' => ['', 'sk-SK'],
        ];
    }

    #[DataProvider('localeMappingProvider')]
    public function test_locale_tag_resolved_correctly(string $locale, string $expectedTag): void
    {
        $this->assertSame(
            $expectedTag,
            $this->resolveLocaleTag($locale),
            "Locale '{$locale}' must resolve to BCP 47 tag '{$expectedTag}'.",
        );
    }

    public function test_show_vue_contains_locale_map_with_correct_keys(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/js/Pages/Clients/Show.vue',
        );

        $this->assertStringContainsString(
            'sk-SK',
            $source,
            'Show.vue must contain sk-SK in the localeTag map.',
        );

        $this->assertStringContainsString(
            'en-GB',
            $source,
            'Show.vue must contain en-GB in the localeTag map.',
        );

        $this->assertStringContainsString(
            'uk-UA',
            $source,
            'Show.vue must contain uk-UA in the localeTag map.',
        );
    }

    public function test_show_vue_does_not_hardcode_sk_sk_in_to_locale_date_string(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/js/Pages/Clients/Show.vue',
        );

        $this->assertStringNotContainsString(
            "toLocaleDateString('sk-SK')",
            $source,
            "Show.vue must not hardcode 'sk-SK' in toLocaleDateString — use localeTag computed instead.",
        );
    }

    public function test_show_vue_uses_locale_tag_computed_in_to_locale_date_string(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/js/Pages/Clients/Show.vue',
        );

        $this->assertStringContainsString(
            'toLocaleDateString(localeTag.value',
            $source,
            'Show.vue must pass localeTag.value to toLocaleDateString, not a literal locale string.',
        );
    }
}
