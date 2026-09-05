<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Regression test: Login.vue compliance fixes.
 *
 * Fixes applied:
 * - Hardcoded Slovak strings replaced with t('auth.*') keys.
 * - Inline hex/rgba styles replaced with CSS var references.
 *
 * These tests verify the login route still responds correctly after those changes.
 */
final class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_returns_200_for_guests(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_login_page_renders_correct_inertia_component(): void
    {
        $response = $this->get(route('login'));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Auth/Login'),
        );
    }

    public function test_login_page_not_accessible_when_authenticated(): void
    {
        $this->actingAsTenantUser('Admin');

        $response = $this->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_vue_uses_translation_keys_not_hardcoded_strings(): void
    {
        $source = (string) file_get_contents(
            base_path('resources/js/Pages/Auth/Login.vue'),
        );

        $hardcodedStrings = [
            'Celá vaša firma',
            'pod kontrolou.',
            'Správa klientov',
            'Bez kreditnej karty',
            'SK podpora',
            'Späť na hlavnú',
            'Vitajte späť',
            '14 dní zadarmo',
        ];

        foreach ($hardcodedStrings as $string) {
            $this->assertStringNotContainsString(
                $string,
                $source,
                "Login.vue must not contain hardcoded Slovak string '{$string}' — use t('auth.*') key instead.",
            );
        }
    }

    public function test_login_vue_uses_t_function_for_auth_hero_keys(): void
    {
        $source = (string) file_get_contents(
            base_path('resources/js/Pages/Auth/Login.vue'),
        );

        $requiredCalls = [
            "t('auth.hero.title_1')",
            "t('auth.hero.title_2')",
            "t('auth.hero.subtitle')",
            "t('auth.hero.feature_free')",
            "t('auth.hero.feature_no_card')",
            "t('auth.hero.feature_support')",
            "t('auth.back_home')",
            "t('auth.welcome_back')",
        ];

        foreach ($requiredCalls as $call) {
            $this->assertStringContainsString(
                $call,
                $source,
                "Login.vue must call {$call} (translation key, not hardcoded string).",
            );
        }
    }

    public function test_login_vue_uses_css_vars_not_inline_hex_for_text_muted(): void
    {
        $source = (string) file_get_contents(
            base_path('resources/js/Pages/Auth/Login.vue'),
        );

        // The fix replaced inline hex/rgba text-muted with var(--auth-text-muted).
        $this->assertStringContainsString(
            'var(--auth-text-muted)',
            $source,
            "Login.vue must reference CSS var '--auth-text-muted' instead of an inline rgba/hex value.",
        );
    }
}
