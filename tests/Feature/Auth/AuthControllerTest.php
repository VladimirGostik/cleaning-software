<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function withActiveMembership(User $user): User
    {
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        return $user;
    }

    public function test_guest_sees_login_page(): void
    {
        $response = $this->withoutVite()->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
            ->has('canResetPassword'),
        );
    }

    public function test_authenticated_user_is_redirected_from_login(): void
    {
        $user = $this->withActiveMembership(User::factory()->create());

        $response = $this->withoutVite()->actingAs($user)->get('/login');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_with_valid_credentials_authenticates_user(): void
    {
        $this->withActiveMembership(User::factory()->create(['email' => 'test@example.com']));

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_login_with_wrong_password_returns_validation_error(): void
    {
        $this->withActiveMembership(User::factory()->create(['email' => 'test@example.com']));

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertInvalid(['email']);
        $this->assertGuest();
    }

    public function test_login_with_nonexistent_email_returns_validation_error(): void
    {
        $response = $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $response->assertInvalid(['email']);
        $this->assertGuest();
    }

    public function test_login_without_active_membership_returns_validation_error(): void
    {
        User::factory()->create(['email' => 'no-tenant@example.com']);

        $response = $this->post('/login', [
            'email' => 'no-tenant@example.com',
            'password' => 'password',
        ]);

        $response->assertInvalid(['email']);
        $this->assertGuest();
    }

    public function test_login_with_inactive_account_returns_validation_error(): void
    {
        $this->withActiveMembership(User::factory()->inactive()->create(['email' => 'inactive@example.com']));

        $response = $this->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertInvalid(['email']);
        $this->assertGuest();
    }

    public function test_login_with_all_memberships_deactivated_returns_validation_error(): void
    {
        $user = User::factory()->create(['email' => 'deactivated@example.com']);
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => false, 'joined_at' => now()]);

        $response = $this->post('/login', [
            'email' => 'deactivated@example.com',
            'password' => 'password',
        ]);

        $response->assertInvalid(['email']);
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
