<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Data\Auth\ResetPasswordData;
use App\Http\Controllers\Auth\NewPasswordController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

final class ResetPasswordDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'token' => 'some-valid-token',
            'email' => 'user@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];
    }

    public function test_reset_password_with_mismatched_passwords_redirects_with_session_error(): void
    {
        // Arrange
        $payload = $this->validPayload();
        $payload['password_confirmation'] = 'DifferentPass999!';

        // Act — web route redirects back with session errors on validation failure
        $response = $this->post(route('password.store'), $payload);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHasErrors('password');
    }

    public function test_reset_password_with_missing_token_redirects_with_session_error(): void
    {
        // Arrange
        $payload = $this->validPayload();
        unset($payload['token']);

        // Act
        $response = $this->post(route('password.store'), $payload);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHasErrors('token');
    }

    public function test_reset_password_with_invalid_email_redirects_with_session_error(): void
    {
        // Arrange
        $payload = $this->validPayload();
        $payload['email'] = 'not-valid';

        // Act
        $response = $this->post(route('password.store'), $payload);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    public function test_reset_password_data_dto_has_confirmed_rule(): void
    {
        // Structural: ResetPasswordData must define rules() with 'confirmed'
        $rules = ResetPasswordData::rules();

        $passwordRules = $rules['password'] ?? [];
        $this->assertContains(
            'confirmed',
            $passwordRules,
            'ResetPasswordData::rules() must include the confirmed rule for the password field',
        );
    }

    public function test_new_password_controller_store_uses_dto_not_request(): void
    {
        $reflection = new ReflectionClass(NewPasswordController::class);
        $storeMethod = $reflection->getMethod('store');

        $params = $storeMethod->getParameters();
        $this->assertCount(1, $params, 'NewPasswordController::store() must accept exactly one parameter (the DTO)');

        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame(
            ResetPasswordData::class,
            $type instanceof ReflectionNamedType ? $type->getName() : (string) $type,
            'NewPasswordController::store() parameter must be ResetPasswordData',
        );
    }

    public function test_reset_password_with_valid_payload_does_not_produce_password_validation_error(): void
    {
        // Arrange & Act — broker will reject invalid token, but DTO validation must pass
        $response = $this->post(route('password.store'), $this->validPayload());

        // Assert — any error must not be about the password field itself
        $response->assertSessionDoesntHaveErrors('password');
    }
}
