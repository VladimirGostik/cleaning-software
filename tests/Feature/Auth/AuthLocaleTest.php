<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: auth error messages must use the active locale (sk/uk),
 * not fall back to English when lang/sk/auth.php and lang/uk/auth.php exist.
 *
 * Bug: lang/sk/auth.php + lang/sk/passwords.php + lang/uk/auth.php +
 * lang/uk/passwords.php were missing; Laravel fell back to 'en'.
 */
final class AuthLocaleTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Translation file integrity — locale strings must differ from English
    // -----------------------------------------------------------------------

    public function test_slovak_auth_failed_string_differs_from_english(): void
    {
        // Assert
        $this->assertNotEquals(
            trans('auth.failed', [], 'en'),
            trans('auth.failed', [], 'sk'),
            'lang/sk/auth.php "failed" key must differ from English — it may be a copy of en/auth.php.',
        );
    }

    public function test_ukrainian_auth_failed_string_differs_from_english(): void
    {
        // Assert
        $this->assertNotEquals(
            trans('auth.failed', [], 'en'),
            trans('auth.failed', [], 'uk'),
            'lang/uk/auth.php "failed" key must differ from English — it may be a copy of en/auth.php.',
        );
    }

    // -----------------------------------------------------------------------
    // HTTP behaviour — login error returned in the correct locale
    // -----------------------------------------------------------------------

    public function test_bad_credentials_with_sk_locale_returns_slovak_error_message(): void
    {
        // Arrange
        User::factory()->create(['email' => 'test-sk@example.com', 'password' => bcrypt('correct')]);
        $expectedMessage = trans('auth.failed', [], 'sk');

        // Act — LocaleMiddleware resolves 'sk' from Accept-Language header
        $response = $this->withHeader('Accept-Language', 'sk')
            ->post(route('login'), [
                'email' => 'test-sk@example.com',
                'password' => 'wrong-password',
            ]);

        // Assert — ValidationException redirects (302) with session error
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email' => $expectedMessage]);
    }

    public function test_bad_credentials_with_uk_locale_returns_ukrainian_error_message(): void
    {
        // Arrange
        User::factory()->create(['email' => 'test-uk@example.com', 'password' => bcrypt('correct')]);
        $expectedMessage = trans('auth.failed', [], 'uk');

        // Act — LocaleMiddleware resolves 'uk' from Accept-Language header
        $response = $this->withHeader('Accept-Language', 'uk')
            ->post(route('login'), [
                'email' => 'test-uk@example.com',
                'password' => 'wrong-password',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email' => $expectedMessage]);
    }

    public function test_bad_credentials_with_sk_locale_does_not_return_english_error_message(): void
    {
        // Arrange
        User::factory()->create(['email' => 'test-sk-noen@example.com', 'password' => bcrypt('correct')]);
        $englishMessage = trans('auth.failed', [], 'en');

        // Act
        $response = $this->withHeader('Accept-Language', 'sk')
            ->post(route('login'), [
                'email' => 'test-sk-noen@example.com',
                'password' => 'wrong-password',
            ]);

        // Assert — English error must NOT appear for sk locale
        $response->assertStatus(302);
        $response->assertSessionDoesntHaveErrors(['email' => $englishMessage]);
    }

    public function test_bad_credentials_with_uk_locale_does_not_return_english_error_message(): void
    {
        // Arrange
        User::factory()->create(['email' => 'test-uk-noen@example.com', 'password' => bcrypt('correct')]);
        $englishMessage = trans('auth.failed', [], 'en');

        // Act
        $response = $this->withHeader('Accept-Language', 'uk')
            ->post(route('login'), [
                'email' => 'test-uk-noen@example.com',
                'password' => 'wrong-password',
            ]);

        // Assert — English error must NOT appear for uk locale
        $response->assertStatus(302);
        $response->assertSessionDoesntHaveErrors(['email' => $englishMessage]);
    }

    // -----------------------------------------------------------------------
    // Edge: session locale takes precedence over Accept-Language header
    // -----------------------------------------------------------------------

    public function test_session_locale_takes_precedence_over_accept_language_header(): void
    {
        // Arrange — session has 'sk'; header says 'en'.
        // LocaleMiddleware priority (unauthenticated): session > cookie > Accept-Language.
        User::factory()->create(['email' => 'session-locale@example.com', 'password' => bcrypt('correct')]);
        $skMessage = trans('auth.failed', [], 'sk');
        $enMessage = trans('auth.failed', [], 'en');

        // Act — session locale 'sk' set before POST; Accept-Language: en is ignored
        $response = $this->withSession(['locale' => 'sk'])
            ->withHeader('Accept-Language', 'en')
            ->post(route('login'), [
                'email' => 'session-locale@example.com',
                'password' => 'wrong-password',
            ]);

        // Assert — sk wins over en header
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email' => $skMessage]);
        $response->assertSessionDoesntHaveErrors(['email' => $enMessage]);
    }
}
