<?php

declare(strict_types=1);

namespace Tests\Feature\Language;

use Tests\TestCase;

final class TranslationParityTest extends TestCase
{
    /** @return array<string, mixed> */
    private function load(string $locale): array
    {
        $path = resource_path("lang/{$locale}/app.json");

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_sk_en_uk_app_json_share_the_same_key_set(): void
    {
        $sk = array_keys($this->load('sk'));
        $en = array_keys($this->load('en'));
        $uk = array_keys($this->load('uk'));

        sort($sk);
        sort($en);
        sort($uk);

        $this->assertSame($sk, $en, 'sk and en app.json key sets differ.');
        $this->assertSame($sk, $uk, 'sk and uk app.json key sets differ.');
    }

    public function test_uk_validation_json_matches_sk_key_set(): void
    {
        $sk = json_decode((string) file_get_contents(resource_path('lang/sk/validation.json')), true, flags: JSON_THROW_ON_ERROR);
        $uk = json_decode((string) file_get_contents(resource_path('lang/uk/validation.json')), true, flags: JSON_THROW_ON_ERROR);

        $skKeys = array_keys($sk);
        $ukKeys = array_keys($uk);
        sort($skKeys);
        sort($ukKeys);

        $this->assertSame($skKeys, $ukKeys);
    }
}
