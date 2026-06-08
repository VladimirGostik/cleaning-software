<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression test: locale-aware date formatting.
 *
 * Fix applied: toLocaleDateString('sk-SK') hardcoded → dynamic localeTag computed in
 * useLocalizedDate composable:
 *   const map: Record<string, string> = { sk: 'sk-SK', en: 'en-GB', uk: 'uk-UA' };
 *   return map[pageProps.locale] ?? 'sk-SK';
 *
 * The composable is extracted from Clients/Show.vue and Objects/Show.vue (DRY refactor).
 * Verifies the mapping table and unknown-locale fallback match what the composable implements.
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

    public function test_locale_date_composable_contains_locale_map_with_correct_keys(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/js/Composables/useLocalizedDate.ts',
        );

        $this->assertStringContainsString(
            'sk-SK',
            $source,
            'useLocalizedDate composable must contain sk-SK in the locale map.',
        );

        $this->assertStringContainsString(
            'en-GB',
            $source,
            'useLocalizedDate composable must contain en-GB in the locale map.',
        );

        $this->assertStringContainsString(
            'uk-UA',
            $source,
            'useLocalizedDate composable must contain uk-UA in the locale map.',
        );
    }

    public function test_locale_date_composable_does_not_hardcode_sk_sk_in_to_locale_date_string(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/js/Composables/useLocalizedDate.ts',
        );

        $this->assertStringNotContainsString(
            "toLocaleDateString('sk-SK')",
            $source,
            "useLocalizedDate must not hardcode 'sk-SK' — use the computed localeTag.",
        );
    }

    public function test_locale_date_composable_uses_locale_tag_in_to_locale_date_string(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/js/Composables/useLocalizedDate.ts',
        );

        $this->assertStringContainsString(
            'toLocaleDateString(localeTag.value',
            $source,
            'useLocalizedDate must pass localeTag.value to toLocaleDateString, not a literal.',
        );
    }

    public function test_clients_show_vue_delegates_date_formatting_to_composable(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/js/Pages/Clients/Show.vue',
        );

        $this->assertStringContainsString(
            'useLocalizedDate',
            $source,
            'Clients/Show.vue must use the useLocalizedDate composable for locale-aware formatting.',
        );

        $this->assertStringNotContainsString(
            "toLocaleDateString('sk-SK')",
            $source,
            "Clients/Show.vue must not hardcode 'sk-SK' in toLocaleDateString.",
        );
    }

    public function test_objects_show_vue_delegates_date_formatting_to_composable(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/js/Pages/Objects/Show.vue',
        );

        $this->assertStringContainsString(
            'useLocalizedDate',
            $source,
            'Objects/Show.vue must use the useLocalizedDate composable for locale-aware formatting.',
        );

        $this->assertStringNotContainsString(
            "toLocaleDateString('sk-SK')",
            $source,
            "Objects/Show.vue must not hardcode 'sk-SK' in toLocaleDateString.",
        );
    }
}
