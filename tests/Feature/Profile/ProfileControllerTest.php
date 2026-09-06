<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private function withActiveMembership(User $user): User
    {
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        return $user;
    }

    public function test_authenticated_user_sees_profile_page(): void
    {
        $user = $this->withActiveMembership(User::factory()->create());

        $response = $this->withoutVite()->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Profile/Show'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect(route('login'));
    }

    public function test_update_changes_profile_data_in_database(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com', 'locale' => 'sk']));

        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'locale' => 'en',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'email' => 'new@example.com', 'locale' => 'en']);
    }

    public function test_update_with_invalid_email_returns_validation_error(): void
    {
        $user = $this->withActiveMembership(User::factory()->create());

        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'Valid Name',
            'email' => 'not-an-email',
            'locale' => 'sk',
        ]);

        $response->assertInvalid(['email']);
    }

    public function test_update_with_missing_name_returns_validation_error(): void
    {
        $user = $this->withActiveMembership(User::factory()->create());

        $response = $this->actingAs($user)->put('/profile', [
            'name' => '',
            'email' => $user->email,
            'locale' => 'sk',
        ]);

        $response->assertInvalid(['name']);
    }

    public function test_change_password_with_correct_current_password_updates_password(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['password' => Hash::make('current-password')]));

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password ?? ''));
    }

    public function test_change_password_with_wrong_current_password_returns_error(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['password' => Hash::make('correct-password')]));

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertInvalid(['current_password']);
    }

    public function test_change_password_with_mismatched_confirmation_returns_validation_error(): void
    {
        $user = $this->withActiveMembership(User::factory()->create(['password' => Hash::make('current-password')]));

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertInvalid(['password']);
    }
}
