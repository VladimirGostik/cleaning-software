<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression test: 8 auth.* translation keys added across sk/en/uk lang files.
 *
 * Fixes: Login.vue hero panel strings were hardcoded Slovak; replaced with t('auth.*') calls.
 * These tests fail if any key is removed or misspelled in any supported locale file.
 */
final class AuthTranslationKeysTest extends TestCase
{
    private const AUTH_KEYS = [
        'auth.hero.title_1',
        'auth.hero.title_2',
        'auth.hero.subtitle',
        'auth.hero.feature_free',
        'auth.hero.feature_no_card',
        'auth.hero.feature_support',
        'auth.back_home',
        'auth.welcome_back',
    ];

    private const LOCALES = ['sk', 'en', 'uk'];

    /**
     * @return array<string, array{string, string, array<string, string>}>
     */
    public static function localeKeyProvider(): array
    {
        $base = dirname(__DIR__, 2) . '/lang';
        $cases = [];

        foreach (self::LOCALES as $locale) {
            $path = "{$base}/{$locale}/app.php";
            $translations = file_exists($path) ? (require $path) : [];
            /** @var array<string, string> $translations */
            foreach (self::AUTH_KEYS as $key) {
                $cases["{$locale} — {$key}"] = [$locale, $key, $translations];
            }
        }

        return $cases;
    }

    /**
     * @param  array<string, string>  $translations
     */
    #[DataProvider('localeKeyProvider')]
    public function test_auth_key_present_in_locale(string $locale, string $key, array $translations): void
    {
        $this->assertArrayHasKey(
            $key,
            $translations,
            "lang/{$locale}/app.php is missing key '{$key}'. Add it to match the Login.vue t('{$key}') call.",
        );
    }

    /**
     * @param  array<string, string>  $translations
     */
    #[DataProvider('localeKeyProvider')]
    public function test_auth_key_value_is_non_empty_string(string $locale, string $key, array $translations): void
    {
        if (! isset($translations[$key])) {
            $this->markTestSkipped("Key '{$key}' absent in {$locale} — covered by test_auth_key_present_in_locale.");
        }

        $this->assertNotEmpty(
            $translations[$key],
            "lang/{$locale}/app.php key '{$key}' must not be an empty string.",
        );
    }

    public function test_sk_lang_file_is_readable(): void
    {
        $path = dirname(__DIR__, 2) . '/lang/sk/app.php';

        $this->assertFileExists($path, 'lang/sk/app.php must exist.');
    }

    public function test_en_lang_file_is_readable(): void
    {
        $path = dirname(__DIR__, 2) . '/lang/en/app.php';

        $this->assertFileExists($path, 'lang/en/app.php must exist.');
    }

    public function test_uk_lang_file_is_readable(): void
    {
        $path = dirname(__DIR__, 2) . '/lang/uk/app.php';

        $this->assertFileExists($path, 'lang/uk/app.php must exist.');
    }
}
