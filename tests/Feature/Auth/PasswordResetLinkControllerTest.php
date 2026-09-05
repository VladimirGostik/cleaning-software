<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PasswordResetLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_forgot_password_page(): void
    {
        $response = $this->withoutVite()->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_authenticated_user_is_redirected_from_forgot_password(): void
    {
        $user = User::factory()->create();

        $response = $this->withoutVite()->actingAs($user)->get('/forgot-password');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_store_with_existing_email_redirects_with_status(): void
    {
        $user = User::factory()->create(['email' => 'exists@example.com']);

        $response = $this->post('/forgot-password', ['email' => 'exists@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('status');
    }

    public function test_store_with_nonexistent_email_still_redirects_with_status(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('status');
    }

    public function test_store_with_invalid_email_format_returns_validation_error(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'not-an-email']);

        $response->assertInvalid(['email']);
    }
}
