<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Data\LoginData;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
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
        $response->assertInertia(fn (AssertableInertia $page) => $page
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

    public function test_login_creates_exactly_one_activity_log_entry(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['email' => 'test@example.com']));

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $loginEntries = DB::table('activity_log')
            ->where('causer_id', $user->id)
            ->where('description', 'login')
            ->count();

        $this->assertSame(1, $loginEntries);
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

    public function test_sixth_wrong_login_for_same_email_and_ip_is_throttled_and_blocks_the_correct_password(): void
    {
        $this->withActiveMembership(User::factory()->create(['email' => 'brute@example.com']));

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.31'])->post('/login', ['email' => 'brute@example.com', 'password' => 'wrong']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.31'])->post('/login', ['email' => 'brute@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.31'])->post('/login', ['email' => 'brute@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_throttle_message_mentions_seconds(): void
    {
        $this->withActiveMembership(User::factory()->create(['email' => 'msg@example.com']));

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.32'])->post('/login', ['email' => 'msg@example.com', 'password' => 'wrong']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.32'])->post('/login', ['email' => 'msg@example.com', 'password' => 'wrong']);

        $this->assertThrottleMessage($this->sessionError('email'));
    }

    public function test_eleventh_login_from_one_ip_with_rotating_emails_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.33'])->post('/login', ['email' => "rot{$i}@example.com", 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.33'])->post('/login', ['email' => 'rot-last@example.com', 'password' => 'wrong']);

        $response->assertSessionHasErrors('email');
        $this->assertThrottleMessage($this->sessionError('email'));
    }

    public function test_precognition_requests_do_not_use_up_the_login_budget(): void
    {
        $this->withActiveMembership(User::factory()->create(['email' => 'pre@example.com']));

        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.34'])->withHeader('Precognition', 'true')->post('/login', ['email' => 'pre@example.com', 'password' => 'wrong']);
        }

        // beforeResolving binds the precognition stub into the container and it persists across requests in one test app
        unset($this->app[LoginData::class]);

        $this->withoutHeader('Precognition')->withServerVariables(['REMOTE_ADDR' => '203.0.113.34'])->post('/login', ['email' => 'pre@example.com', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_precognition_with_correct_credentials_does_not_log_in(): void
    {
        $this->withActiveMembership(User::factory()->create(['email' => 'pre2@example.com']));

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.35'])->withHeader('Precognition', 'true')->post('/login', ['email' => 'pre2@example.com', 'password' => 'password'])->assertNoContent();
        $this->assertGuest();
    }

    public function test_login_with_array_email_is_a_validation_error_not_a_server_error(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.36'])->post('/login', ['email' => ['x'], 'password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    public function test_email_case_variants_share_one_login_bucket(): void
    {
        $this->withActiveMembership(User::factory()->create(['email' => 'case@example.com']));

        foreach (['case@example.com', 'CASE@example.com', 'Case@Example.com', 'case@EXAMPLE.com', 'CASE@EXAMPLE.COM'] as $email) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.37'])->post('/login', ['email' => $email, 'password' => 'wrong']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.37'])->post('/login', ['email' => 'case@example.com', 'password' => 'password']);
        $this->assertThrottleMessage($this->sessionError('email'));
        $this->assertGuest();
    }
}
