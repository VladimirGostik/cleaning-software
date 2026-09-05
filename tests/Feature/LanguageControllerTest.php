<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LanguageControllerTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // Happy paths
    // ──────────────────────────────────────────────

    public function test_switches_locale_and_redirects_to_previous_same_host_url(): void
    {
        $this->actingAsTenantUser('Admin');
        $appUrl = config('app.url');

        $response = $this->get(
            route('language.switch', ['locale' => 'en']),
            ['HTTP_REFERER' => $appUrl . '/dashboard'],
        );

        $response->assertRedirect($appUrl . '/dashboard');
    }

    public function test_stores_locale_in_session(): void
    {
        $this->actingAsTenantUser('Admin');

        $this->get(route('language.switch', ['locale' => 'en']));

        $this->assertEquals('en', session('locale'));
    }

    public function test_persists_locale_on_authenticated_user(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $this->get(route('language.switch', ['locale' => 'en']));

        $this->assertEquals('en', $user->fresh()->locale);
    }

    public function test_sets_locale_cookie(): void
    {
        $this->actingAsTenantUser('Admin');

        $response = $this->get(route('language.switch', ['locale' => 'sk']));

        $response->assertCookie('locale', 'sk');
    }

    public function test_guest_can_switch_locale(): void
    {
        $response = $this->get(route('language.switch', ['locale' => 'sk']));

        $response->assertRedirect();
        $response->assertCookie('locale', 'sk');
    }

    // ──────────────────────────────────────────────
    // Fallback / unsupported locale
    // ──────────────────────────────────────────────

    public function test_unsupported_locale_falls_back_to_default(): void
    {
        $this->actingAsTenantUser('Admin');

        $this->get(route('language.switch', ['locale' => 'xx']));

        $this->assertEquals('sk', session('locale'));
    }

    // ──────────────────────────────────────────────
    // Open-redirect security: external Referer blocked
    // ──────────────────────────────────────────────

    public function test_external_referer_redirects_to_dashboard_not_off_site(): void
    {
        $this->actingAsTenantUser('Admin');

        $response = $this->get(
            route('language.switch', ['locale' => 'sk']),
            ['HTTP_REFERER' => 'https://evil.com/steal'],
        );

        $response->assertRedirect('/dashboard');
        $this->assertStringNotContainsString('evil.com', $response->headers->get('Location') ?? '');
    }

    public function test_referer_with_different_subdomain_redirects_to_dashboard(): void
    {
        $this->actingAsTenantUser('Admin');

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $attackUrl = 'https://attacker.' . $appHost . '/steal';

        $response = $this->get(
            route('language.switch', ['locale' => 'sk']),
            ['HTTP_REFERER' => $attackUrl],
        );

        $response->assertRedirect('/dashboard');
    }

    public function test_language_loop_referer_redirects_to_dashboard(): void
    {
        $this->actingAsTenantUser('Admin');
        $appUrl = config('app.url');

        $response = $this->get(
            route('language.switch', ['locale' => 'en']),
            ['HTTP_REFERER' => $appUrl . '/language/sk'],
        );

        $response->assertRedirect('/dashboard');
    }

    public function test_no_referer_header_stays_on_same_host(): void
    {
        $this->actingAsTenantUser('Admin');

        // No Referer header → url()->previous() returns the app base URL (same host).
        // The guard must NOT redirect off-site; any same-host destination is acceptable.
        $response = $this->get(route('language.switch', ['locale' => 'en']));

        $location = $response->headers->get('Location') ?? '';
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $redirectHost = parse_url($location, PHP_URL_HOST);

        $response->assertRedirect();
        $this->assertNotNull($redirectHost);
        $this->assertSame($appHost, $redirectHost);
    }
}
