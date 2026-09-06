<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private function withActiveMembership(User $user): User
    {
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        return $user;
    }

    // ── login ─────────────────────────────────────────────────────────────────

    public function test_login_with_valid_credentials_returns_token_and_user(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['email' => 'test@example.com']));

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'is_active', 'locale', 'roles', 'created_at'],
        ]);
        $response->assertJsonPath('user.email', 'test@example.com');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_with_wrong_password_returns_422(): void
    {
        $this->withActiveMembership(User::factory()->create(['email' => 'test@example.com']));

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_with_nonexistent_email_returns_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_with_invalid_email_format_returns_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_with_missing_fields_returns_422(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_without_active_membership_returns_422(): void
    {
        User::factory()->create(['email' => 'no-tenant@example.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'no-tenant@example.com',
            'password' => 'password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_with_inactive_account_returns_422(): void
    {
        $user = $this->withActiveMembership(User::factory()->inactive()->create(['email' => 'inactive@example.com']));

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    // ── logout ────────────────────────────────────────────────────────────────

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/auth/logout');

        $response->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_only_revokes_current_token_not_others(): void
    {
        $user = User::factory()->create();
        $user->createToken('other');
        $token = $user->createToken('current')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertUnauthorized();
    }
}
