<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class PasswordResetLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_forgot_password_page(): void
    {
        $response = $this->withoutVite()->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ForgotPassword'));
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

    public function test_sixth_forgot_password_request_from_one_ip_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])->post('/forgot-password', ['email' => "n{$i}@example.com"])->assertSessionHas('status');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])->post('/forgot-password', ['email' => 'n6@example.com'])->assertSessionHasErrors('email');
    }

    public function test_existing_and_unknown_email_get_the_same_status_message(): void
    {
        User::factory()->create(['email' => 'same@example.com']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.43'])->post('/forgot-password', ['email' => 'same@example.com'])
            ->assertSessionHas('status', __('passwords.sent'));
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.43'])->post('/forgot-password', ['email' => 'ghost@example.com'])
            ->assertSessionHas('status', __('passwords.sent'));
    }
}
