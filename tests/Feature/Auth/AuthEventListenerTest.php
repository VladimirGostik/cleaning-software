<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

final class AuthEventListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_event_writes_auth_activity_log(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        event(new Login('web', $user, false));

        // Assert
        $exists = Activity::query()
            ->where('log_name', 'auth')
            ->where('event', 'login')
            ->where('causer_id', $user->id)
            ->exists();

        $this->assertTrue($exists, 'Expected an auth activity log entry for the login event');
    }

    public function test_logout_event_writes_auth_activity_log(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        event(new Logout('web', $user));

        // Assert
        $exists = Activity::query()
            ->where('log_name', 'auth')
            ->where('event', 'logout')
            ->where('causer_id', $user->id)
            ->exists();

        $this->assertTrue($exists, 'Expected an auth activity log entry for the logout event');
    }

    public function test_logout_with_null_user_does_not_write_log(): void
    {
        // Act
        event(new Logout('web', null));

        // Assert
        $count = Activity::query()
            ->where('log_name', 'auth')
            ->where('event', 'logout')
            ->count();

        $this->assertSame(0, $count, 'Null-user logout must not produce an activity entry');
    }

    public function test_failed_login_event_writes_auth_activity_log_without_causer(): void
    {
        // Arrange
        $credentials = ['email' => 'unknown@example.com', 'password' => 'wrong'];

        // Act
        event(new Failed('web', null, $credentials));

        // Assert
        $entry = Activity::query()
            ->where('log_name', 'auth')
            ->where('event', 'failed')
            ->first();

        $this->assertNotNull($entry, 'Expected a failed-login activity log entry');
        $this->assertNull($entry->causer_id, 'Failed login with no user must have null causer_id');

        /** @var array<string, mixed> $props */
        $props = $entry->properties->toArray();
        $this->assertSame('unknown@example.com', $props['attempted_email'] ?? null);
    }

    public function test_successful_login_via_http_writes_activity_log(): void
    {
        // Arrange
        $user = User::factory()->create(['password' => bcrypt('password')]);

        // Act
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        // Assert
        $exists = Activity::query()
            ->where('log_name', 'auth')
            ->where('event', 'login')
            ->where('causer_id', $user->id)
            ->exists();

        $this->assertTrue($exists, 'HTTP login must write an auth activity log entry');
    }
}
