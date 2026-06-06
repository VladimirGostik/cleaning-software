<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Data\Auth\ForgotPasswordData;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

final class ForgotPasswordDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_with_valid_email_for_existing_user_succeeds(): void
    {
        // Arrange
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);

        // Act
        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        // Assert — redirect back with success flash, no field errors
        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors('email');
    }

    public function test_forgot_password_with_invalid_email_redirects_with_session_error(): void
    {
        // Arrange & Act — web route returns redirect back with session errors (not JSON 422)
        $response = $this->post(route('password.email'), [
            'email' => 'not-an-email',
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    public function test_forgot_password_with_missing_email_redirects_with_session_error(): void
    {
        // Arrange & Act
        $response = $this->post(route('password.email'), []);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    public function test_forgot_password_uses_dto_not_request_validate(): void
    {
        // Structural: controller must type-hint ForgotPasswordData, not Request
        $reflection = new ReflectionClass(PasswordResetLinkController::class);
        $storeMethod = $reflection->getMethod('store');

        $params = $storeMethod->getParameters();
        $this->assertCount(1, $params, 'store() must accept exactly one parameter (the DTO)');

        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame(
            ForgotPasswordData::class,
            $type instanceof ReflectionNamedType ? $type->getName() : (string) $type,
            'store() parameter must be ForgotPasswordData, not Request or array',
        );
    }
}
