<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Activity;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class AuthActivityLogTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $attributes */
    private function member(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        return $user;
    }

    private function ip(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    public function test_throttled_web_login_logs_a_single_lockout_without_causer(): void
    {
        $user = $this->member(['email' => 'lock@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->ip('203.0.113.21')->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }
        $this->ip('203.0.113.21')->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->ip('203.0.113.21')->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');

        $row = Activity::query()->where('description', 'login_lockout')->sole();
        $this->assertNull($row->causer_id);
        $this->assertSame('lock@example.com', data_get($row->properties, 'email'));
        $this->assertSame('203.0.113.21', data_get($row->properties, 'ip'));
        $this->assertNotNull(data_get($row->properties, 'user_agent'));
    }

    public function test_throttled_mobile_login_still_returns_json_429_and_logs_lockout(): void
    {
        $user = $this->member(['email' => 'mob@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->ip('203.0.113.22')->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->ip('203.0.113.22')->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();

        $this->assertSame(1, Activity::query()->where('description', 'login_lockout')->count());
    }

    public function test_successful_reset_logs_password_reset_with_causer(): void
    {
        $user = $this->member(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $this->ip('203.0.113.23')->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('login'));

        $row = Activity::query()->where('description', 'password_reset')->sole();
        $this->assertSame($user->id, $row->causer_id);
        $this->assertSame('203.0.113.23', data_get($row->properties, 'ip'));
    }

    public function test_reset_link_request_logs_for_existing_email_only(): void
    {
        $user = $this->member(['email' => 'link@example.com']);

        $this->ip('203.0.113.24')->post('/forgot-password', ['email' => $user->email]);
        $this->ip('203.0.113.24')->post('/forgot-password', ['email' => 'nobody@example.com']);

        $row = Activity::query()->where('description', 'password_reset_link_sent')->sole();
        $this->assertSame($user->id, $row->causer_id);
        $this->assertSame('link@example.com', data_get($row->properties, 'email'));
    }

    public function test_web_password_change_logs_password_changed(): void
    {
        $user = $this->member(['password' => Hash::make('current-password')]);

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect();

        $row = Activity::query()->where('description', 'password_changed')->sole();
        $this->assertSame($user->id, $row->causer_id);
    }

    public function test_api_password_change_logs_password_changed(): void
    {
        $user = $this->member(['password' => Hash::make('current-password')]);
        $this->actingAsMobile($user);

        $this->putJson('/api/profile/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertNoContent();

        $this->assertSame(1, Activity::query()->where('description', 'password_changed')->where('causer_id', $user->id)->count());
    }

    public function test_api_login_with_array_email_returns_422(): void
    {
        $this->ip('203.0.113.25')->postJson('/api/auth/login', ['email' => ['a'], 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_lockout_email_is_truncated(): void
    {
        $email = str_repeat('a', 400).'@example.com';

        $this->ip('203.0.113.26')->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong']);
        for ($i = 0; $i < 10; $i++) {
            $this->ip('203.0.113.26')->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong']);
        }

        $row = Activity::query()->where('description', 'login_lockout')->sole();
        $this->assertIsString($stored = data_get($row->properties, 'email'));
        $this->assertSame(255, mb_strlen($stored));
    }

    public function test_rotating_emails_from_one_ip_write_one_lockout_row(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->ip('203.0.113.27')->post('/login', ['email' => "rot{$i}@example.com", 'password' => 'wrong']);
        }

        $this->assertSame(1, Activity::query()->where('description', 'login_lockout')->count());
    }
}
