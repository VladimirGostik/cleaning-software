<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ProfileApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private function withActiveMembership(User $user): User
    {
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        return $user;
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_authenticated_user_profile(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']));
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertOk();
        $response->assertJsonStructure(['id', 'name', 'email', 'is_active', 'locale', 'roles', 'created_at']);
        $response->assertJsonPath('email', 'jane@example.com');
        $response->assertJsonPath('name', 'Jane Doe');
    }

    public function test_show_requires_authentication(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertUnauthorized();
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_changes_profile_data_in_database(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com', 'locale' => 'sk']));
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'locale' => 'en',
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'New Name');
        $response->assertJsonPath('email', 'new@example.com');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
            'locale' => 'en',
        ]);
    }

    public function test_update_with_missing_name_returns_422(): void
    {
        $user = $this->withActiveMembership(User::factory()->create());
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', [
            'name' => '',
            'email' => $user->email,
            'locale' => 'sk',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_update_with_invalid_email_returns_422(): void
    {
        $user = $this->withActiveMembership(User::factory()->create());
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile', [
            'name' => 'Valid Name',
            'email' => 'not-an-email',
            'locale' => 'sk',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_update_requires_authentication(): void
    {
        $response = $this->putJson('/api/profile', [
            'name' => 'Name',
            'email' => 'email@example.com',
            'locale' => 'sk',
        ]);

        $response->assertUnauthorized();
    }

    // ── changePassword ────────────────────────────────────────────────────────

    public function test_change_password_with_correct_current_password_succeeds(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['password' => Hash::make('current-password')]));
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password ?? ''));
    }

    public function test_change_password_with_wrong_current_password_returns_422(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['password' => Hash::make('correct-password')]));
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function test_change_password_with_mismatched_confirmation_returns_422(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['password' => Hash::make('current-password')]));
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_change_password_with_too_short_password_returns_422(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['password' => Hash::make('current-password')]));
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'current-password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertUnauthorized();
    }
}
