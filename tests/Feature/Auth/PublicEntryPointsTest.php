<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Landing page + self-service registration removed — see
 * .claude/plans/remove-entitlement-layer.md Part B.
 */
final class PublicEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // GET / — happy
    // -------------------------------------------------------------------------

    public function test_guest_visiting_root_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_visiting_root_is_redirected_to_dashboard(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $this->actingAs($user);

        $this->get('/')->assertRedirect(route('dashboard'));
    }

    // -------------------------------------------------------------------------
    // GET / — edge: authenticated user with no active tenant still redirects
    // to dashboard, not a 500.
    // -------------------------------------------------------------------------

    public function test_authenticated_user_with_no_active_tenant_still_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/')->assertRedirect(route('dashboard'));
    }

    // -------------------------------------------------------------------------
    // GET|POST /register — removed, 404 for guest and authenticated
    // -------------------------------------------------------------------------

    public function test_register_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('register'));
    }

    public function test_guest_get_register_returns_404(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_guest_post_register_returns_404(): void
    {
        $this->post('/register', [])->assertNotFound();
    }

    public function test_authenticated_get_register_returns_404(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $this->actingAs($user);

        $this->get('/register')->assertNotFound();
    }

    public function test_authenticated_post_register_returns_404(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $this->actingAs($user);

        $this->post('/register', [])->assertNotFound();
    }
}
