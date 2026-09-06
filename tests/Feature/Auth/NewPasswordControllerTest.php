<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class NewPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_reset_password_page_with_token(): void
    {
        $token = 'test-token-value';

        $response = $this->withoutVite()->get("/reset-password/{$token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', $token),
        );
    }

    public function test_store_with_valid_token_resets_password_and_redirects_to_login(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password ?? ''));
    }

    public function test_store_with_invalid_token_redirects_with_email_error(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'test@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    public function test_store_with_mismatched_passwords_returns_validation_error(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertInvalid(['password']);
    }

    public function test_store_with_missing_required_fields_returns_validation_error(): void
    {
        $response = $this->post('/reset-password', []);

        $response->assertInvalid(['token', 'email', 'password']);
    }
}
