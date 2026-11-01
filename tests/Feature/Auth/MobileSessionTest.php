<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\MobileSessionRevokeReasonEnum;
use App\Enums\PermissionEnum;
use App\Models\Activity;
use App\Models\DeviceToken;
use App\Models\MobileSession;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DeviceTokenService;
use App\Services\MobileSessionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class MobileSessionTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $firm;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->firm = $this->firm();
        $this->user = $this->member($this->firm, 'Admin');
        $this->consoleContext();
    }

    /** @return array{token: string, refresh: string} */
    private function login(?User $user = null): array
    {
        $this->app['auth']->shouldUse('web');
        $response = $this->call('POST', '/api/auth/login', ['email' => ($user ?? $this->user)->email, 'password' => 'password'], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $response->assertOk();

        // The attempt leaves a web login in the test session store; real API calls carry no session.
        $this->app['session.store']->flush();
        $this->app['auth']->forgetGuards();

        return ['token' => $this->stringAt($response, 'token'), 'refresh' => $this->stringAt($response, 'refresh_token')];
    }

    private function http(?string $token = null): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $token === null ? $this : $this->withToken($token);
    }

    /** @return TestResponse<JsonResponse> */
    private function refresh(string $refresh): TestResponse
    {
        return $this->http()->postJson('/api/auth/refresh', ['refresh_token' => $refresh]);
    }

    /** @return TestResponse<JsonResponse> */
    private function revokeToken(string $refresh): TestResponse
    {
        return $this->http()->postJson('/api/auth/revoke', ['refresh_token' => $refresh]);
    }

    /** @return TestResponse<JsonResponse> */
    private function me(string $token): TestResponse
    {
        return $this->http($token)->getJson('/api/me');
    }

    /** @param TestResponse<JsonResponse> $response */
    private function stringAt(TestResponse $response, string $key): string
    {
        $value = $response->json($key);
        $this->assertIsString($value);

        return $value;
    }

    /** @return array{token: string, refresh_token: string} */
    private function renewed(string $refresh): array
    {
        $response = $this->refresh($refresh)->assertOk();

        return ['token' => $this->stringAt($response, 'token'), 'refresh_token' => $this->stringAt($response, 'refresh_token')];
    }

    private function registerDevice(string $access, string $fcm): void
    {
        $this->http($access)->postJson('/api/devices', ['token' => $fcm, 'platform' => 'ios'])->assertNoContent();
    }

    private function sessionOf(string $refresh): ?MobileSession
    {
        return MobileSession::query()->find(explode('.', $refresh)[0]);
    }

    private function revokedAudits(string $reason): int
    {
        return Activity::query()->where('description', 'mobile_session_revoked')->where('properties->reason', $reason)->count();
    }

    public function test_login_returns_the_pair_and_the_database_holds_only_hashes(): void
    {
        $pair = $this->login();

        [$sessionId, $secret] = explode('.', $pair['refresh'], 2);
        $session = MobileSession::query()->findOrFail($sessionId);

        $this->assertSame(hash('sha256', $secret), $session->refresh_hash);
        $this->assertNull($session->previous_refresh_hash);
        $this->assertEqualsWithDelta(now()->addDays(365)->timestamp, $session->expires_at->timestamp, 5);

        $dump = json_encode([
            DB::table('mobile_sessions')->get(),
            DB::table('personal_access_tokens')->get(),
            DB::table('device_tokens')->get(),
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($secret, $dump);
        $this->assertStringNotContainsString(explode('|', $pair['token'], 2)[1], $dump);

        $token = PersonalAccessToken::findToken($pair['token']);
        $this->assertSame($token?->getKey(), $session->personal_access_token_id);
        $this->assertEqualsWithDelta(now()->addMinutes(60)->timestamp, $token?->expires_at?->timestamp, 5);
    }

    public function test_login_response_shape_has_exactly_token_refresh_token_and_user(): void
    {
        $response = $this->postJson('/api/auth/login', ['email' => $this->user->email, 'password' => 'password']);

        $response->assertOk()->assertJsonStructure(['token', 'refresh_token', 'user' => ['id', 'email']]);
        $this->assertEqualsCanonicalizing(['token', 'refresh_token', 'user'], array_keys((array) $response->json()));
    }

    public function test_access_token_works_at_59_minutes_and_is_401_at_61_and_refresh_restores_access(): void
    {
        $pair = $this->login();

        $this->travel(59)->minutes();
        $this->me($pair['token'])->assertOk();

        $this->travel(2)->minutes();
        $this->me($pair['token'])->assertUnauthorized();

        $renewed = $this->renewed($pair['refresh']);
        $this->me($renewed['token'])->assertOk();
    }

    public function test_refused_logins_create_no_session_and_no_token(): void
    {
        $inactive = User::factory()->inactive()->create();
        $this->member($this->firm, 'Admin', $inactive);
        $loner = User::factory()->create();
        $cleaner = $this->member($this->firm, 'Interná upratovačka');
        $this->consoleContext();

        foreach ([[$this->user->email, 'wrong'], [$inactive->email, 'password'], [$loner->email, 'password'], [$cleaner->email, 'password']] as [$email, $password]) {
            $this->http()->postJson('/api/auth/login', ['email' => $email, 'password' => $password])->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $this->assertSame(0, MobileSession::query()->count());
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_login_is_throttled_per_email_and_ip_and_an_attacker_cannot_lock_the_owner_out(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson('/api/auth/login', ['email' => $this->user->email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson('/api/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertTooManyRequests();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->postJson('/api/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertOk();
    }

    public function test_login_is_capped_per_ip_across_emails(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => "other{$i}@example.com", 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['email' => 'last@example.com', 'password' => 'wrong'])->assertTooManyRequests();
        $this->assertSame(0, MobileSession::query()->count());
    }

    public function test_a_forged_x_real_ip_header_neither_gets_a_fresh_bucket_nor_reaches_the_audit_row(): void
    {
        $pair = $this->login();
        $this->http()->withHeader('X-Real-IP', '6.6.6.6')->postJson('/api/auth/revoke', ['refresh_token' => $pair['refresh']])->assertNoContent();
        $activity = Activity::query()->where('description', 'mobile_session_revoked')->sole();
        $this->assertNotSame('6.6.6.6', $activity->properties['ip'] ?? null);

        for ($i = 0; $i < 29; $i++) {
            $this->http()->withHeader('X-Real-IP', "10.0.0.{$i}")->postJson('/api/auth/refresh', ['refresh_token' => 'junk'])->assertUnauthorized();
        }
        $this->http()->withHeader('X-Real-IP', '10.9.9.9')->postJson('/api/auth/refresh', ['refresh_token' => 'junk'])->assertTooManyRequests();
    }

    public function test_refresh_returns_a_new_pair_and_the_old_access_token_dies_at_once(): void
    {
        $pair = $this->login();

        $renewed = $this->renewed($pair['refresh']);

        $this->assertNotSame($pair['token'], $renewed['token']);
        $this->assertNotSame($pair['refresh'], $renewed['refresh_token']);
        $this->assertSame(explode('.', $pair['refresh'])[0], explode('.', $renewed['refresh_token'])[0]);
        $this->me($pair['token'])->assertUnauthorized();
        $this->me($renewed['token'])->assertOk();
        $this->assertSame(1, PersonalAccessToken::query()->count());
        $this->assertSame(1, MobileSession::query()->count());
    }

    public function test_every_refresh_slides_the_expiry_to_365_days(): void
    {
        $pair = $this->login();

        $this->travel(100)->days();
        $second = $this->renewed($pair['refresh']);
        $this->assertEqualsWithDelta(now()->addDays(365)->timestamp, $this->sessionOf($second['refresh_token'])?->expires_at->timestamp, 5);

        $this->travel(50)->days();
        $third = $this->renewed($second['refresh_token']);
        $this->assertEqualsWithDelta(now()->addDays(365)->timestamp, $this->sessionOf($third['refresh_token'])?->expires_at->timestamp, 5);
    }

    public function test_old_refresh_after_confirmed_rotation_and_past_the_leeway_kills_the_session(): void
    {
        $pair = $this->login();
        $this->registerDevice($pair['token'], 'fcm-1');
        $renewed = $this->renewed($pair['refresh']);
        $this->me($renewed['token'])->assertOk();

        $this->travel(2)->minutes();
        $this->refresh($pair['refresh'])->assertUnauthorized()->assertJsonStructure(['message']);

        $this->assertNull($this->sessionOf($pair['refresh']));
        $this->assertSame(0, DeviceToken::query()->count());
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertSame(1, $this->revokedAudits('reuse_detected'));
        $this->refresh($renewed['refresh_token'])->assertUnauthorized();
    }

    public function test_an_older_generation_kills_the_session_even_inside_the_leeway(): void
    {
        $r1 = $this->login();
        $r2 = $this->renewed($r1['refresh']);
        $this->me($r2['token'])->assertOk();
        $r3 = $this->renewed($r2['refresh_token']);
        $this->me($r3['token'])->assertOk();

        $this->refresh($r1['refresh'])->assertUnauthorized();

        $this->assertNull($this->sessionOf($r1['refresh']));
        $this->assertSame(1, $this->revokedAudits('reuse_detected'));
    }

    public function test_a_killed_app_that_never_used_the_new_access_token_keeps_its_session(): void
    {
        $r1 = $this->login();
        $this->registerDevice($r1['token'], 'fcm-1');
        $r2 = $this->renewed($r1['refresh']);

        $this->travel(5)->hours();
        $again = $this->renewed($r1['refresh']);

        $this->assertSame($r2['refresh_token'], $again['refresh_token']);
        $this->assertSame(1, MobileSession::query()->count());
        $this->assertSame(1, DeviceToken::query()->count());
        $this->me($again['token'])->assertOk();
    }

    public function test_a_pruned_unused_access_token_still_allows_the_old_refresh_but_a_pruned_used_one_kills(): void
    {
        $r1 = $this->login();
        $this->renewed($r1['refresh']);

        $this->travel(30)->hours();
        $this->artisanCommand('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->refresh($r1['refresh'])->assertOk();
        $this->assertNotNull($this->sessionOf($r1['refresh']));

        $fresh = $this->login();
        $next = $this->renewed($fresh['refresh']);
        $this->me($next['token'])->assertOk();
        $this->travel(30)->hours();
        $this->artisanCommand('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();
        $this->refresh($fresh['refresh'])->assertUnauthorized();
        $this->assertNull($this->sessionOf($fresh['refresh']));
    }

    public function test_parallel_refresh_with_the_same_token_returns_the_same_next_token_and_heals(): void
    {
        $r1 = $this->login();

        $first = $this->renewed($r1['refresh']);
        $second = $this->renewed($r1['refresh']);

        $this->assertSame($first['refresh_token'], $second['refresh_token']);
        $this->assertNotSame($first['token'], $second['token']);
        $this->me($first['token'])->assertUnauthorized();
        $this->me($second['token'])->assertOk();
        $this->assertSame(1, MobileSession::query()->count());

        $healed = $this->renewed($first['refresh_token']);
        $this->me($healed['token'])->assertOk();
        $this->assertSame(0, $this->revokedAudits('reuse_detected'));
    }

    public function test_the_old_refresh_replayed_inside_the_leeway_after_the_new_token_was_used_is_accepted(): void
    {
        $r1 = $this->login();
        $r2 = $this->renewed($r1['refresh']);
        $this->me($r2['token'])->assertOk();

        $this->travel(30)->seconds();
        $replay = $this->renewed($r1['refresh']);

        $this->assertSame($r2['refresh_token'], $replay['refresh_token']);
        $this->assertNotNull($this->sessionOf($r1['refresh']));
    }

    public function test_malformed_and_unknown_refresh_tokens_answer_401_and_kill_nothing(): void
    {
        $pair = $this->login();
        $other = $this->member($this->firm, 'Admin');
        $this->consoleContext();
        $otherPair = $this->login($other);
        $unknownId = '0199e0f0-0000-7000-8000-000000000000';

        foreach (['garbage', 'no-dot-here', "{$unknownId}.", 'not-a-uuid.secret', '.secret', "{$unknownId}.secret", str_repeat('a', 255)] as $bad) {
            $this->refresh($bad)->assertUnauthorized()->assertJsonStructure(['message']);
        }

        $this->assertSame(2, MobileSession::query()->count());
        $this->me($pair['token'])->assertOk();
        $this->me($otherPair['token'])->assertOk();
    }

    public function test_a_wrong_secret_on_another_users_session_never_returns_tokens(): void
    {
        $victim = $this->login();
        $sessionId = explode('.', $victim['refresh'])[0];

        $this->refresh("{$sessionId}.".str_repeat('x', 64))->assertUnauthorized()->assertJsonMissingPath('token');
    }

    public function test_refresh_and_revoke_validate_the_body(): void
    {
        $this->http()->postJson('/api/auth/refresh', [])->assertUnprocessable()->assertJsonValidationErrors('refresh_token');
        $this->http()->postJson('/api/auth/refresh', ['refresh_token' => str_repeat('a', 256)])->assertUnprocessable();
        $this->http()->postJson('/api/auth/revoke', [])->assertUnprocessable()->assertJsonValidationErrors('refresh_token');
        $this->http()->postJson('/api/auth/revoke', ['refresh_token' => str_repeat('a', 256)])->assertUnprocessable();
    }

    public function test_the_refresh_throttle_answers_429_and_never_401_even_with_a_valid_token(): void
    {
        $pair = $this->login();

        for ($i = 0; $i < 30; $i++) {
            $this->refresh('junk')->assertUnauthorized();
        }

        $this->refresh($pair['refresh'])->assertTooManyRequests();
        $this->revokeToken($pair['refresh'])->assertTooManyRequests();
        $this->assertNotNull($this->sessionOf($pair['refresh']));
    }

    public function test_a_refresh_token_is_not_a_bearer_token(): void
    {
        $pair = $this->login();

        $this->me($pair['refresh'])->assertUnauthorized();
        $this->http($pair['refresh'])->postJson('/api/auth/logout')->assertUnauthorized();
        $this->assertNotNull($this->sessionOf($pair['refresh']));
    }

    public function test_refresh_works_at_364_days_and_slides(): void
    {
        $pair = $this->login();

        $this->travel(364)->days();
        $renewed = $this->renewed($pair['refresh']);

        $this->travel(364)->days();
        $this->refresh($renewed['refresh_token'])->assertOk();
    }

    public function test_refresh_at_366_days_is_401_and_removes_the_session_and_device_without_a_prune(): void
    {
        $pair = $this->login();
        $this->registerDevice($pair['token'], 'fcm-1');

        $this->travel(366)->days();
        $this->refresh($pair['refresh'])->assertUnauthorized();

        $this->assertNull($this->sessionOf($pair['refresh']));
        $this->assertSame(0, DeviceToken::query()->count());
        $this->assertSame(1, $this->revokedAudits('expired'));
    }

    public function test_the_cleanup_command_removes_only_expired_sessions_and_their_devices(): void
    {
        $alive = $this->login();
        $this->registerDevice($alive['token'], 'alive');
        $dead = $this->login();
        $this->registerDevice($dead['token'], 'dead');
        $this->sessionOf($dead['refresh'])?->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->artisanCommand('cleanup:mobile-sessions')->assertSuccessful();

        $this->assertNull($this->sessionOf($dead['refresh']));
        $this->assertNotNull($this->sessionOf($alive['refresh']));
        $this->assertSame(['alive'], DeviceToken::query()->pluck('token')->all());
        $this->me($dead['token'])->assertUnauthorized();
        $this->me($alive['token'])->assertOk();
    }

    public function test_revoke_with_an_expired_access_token_removes_session_pat_and_device(): void
    {
        $pair = $this->login();
        $this->registerDevice($pair['token'], 'fcm-1');
        $this->travel(3)->hours();

        $this->revokeToken($pair['refresh'])->assertNoContent();

        $this->assertSame(0, MobileSession::query()->count());
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertSame(0, DeviceToken::query()->count());
        $this->refresh($pair['refresh'])->assertUnauthorized();
        $this->me($pair['token'])->assertUnauthorized();
        $this->assertSame(1, $this->revokedAudits('logout'));
    }

    public function test_revoke_is_idempotent_and_never_touches_another_session(): void
    {
        $pair = $this->login();
        $other = $this->login();

        $this->revokeToken($pair['refresh'])->assertNoContent();
        $this->revokeToken($pair['refresh'])->assertNoContent();
        $this->revokeToken('garbage')->assertNoContent();
        $this->revokeToken('0199e0f0-0000-7000-8000-000000000000.secret')->assertNoContent();
        $this->revokeToken(explode('.', $other['refresh'])[0].'.wrong-secret')->assertNoContent();

        $this->assertNotNull($this->sessionOf($other['refresh']));
        $this->me($other['token'])->assertOk();
    }

    public function test_revoking_a_stale_session_object_kills_the_token_issued_by_a_concurrent_refresh(): void
    {
        $pair = $this->login();
        $stale = $this->sessionOf($pair['refresh']);
        $this->assertNotNull($stale);
        $staleTokenId = $stale->personal_access_token_id;

        $this->renewed($pair['refresh']);
        $this->assertNotSame($staleTokenId, $this->sessionOf($pair['refresh'])?->personal_access_token_id);
        $this->assertSame(1, PersonalAccessToken::query()->count());

        app(MobileSessionService::class)->revoke($stale, MobileSessionRevokeReasonEnum::PasswordChanged);

        $this->assertNull($this->sessionOf($pair['refresh']));
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertSame(1, $this->revokedAudits('password_changed'));
    }

    public function test_revoking_a_session_that_is_already_gone_neither_fails_nor_audits_twice(): void
    {
        $pair = $this->login();
        $session = $this->sessionOf($pair['refresh']);
        $this->assertNotNull($session);

        $service = app(MobileSessionService::class);
        $service->revoke($session, MobileSessionRevokeReasonEnum::PasswordChanged);
        $service->revoke($session, MobileSessionRevokeReasonEnum::PasswordChanged);

        $this->assertSame(1, $this->revokedAudits('password_changed'));
    }

    public function test_revoke_accepts_the_previous_refresh_token_right_after_rotation(): void
    {
        $r1 = $this->login();
        $r2 = $this->renewed($r1['refresh']);

        $this->revokeToken($r1['refresh'])->assertNoContent();

        $this->assertNull($this->sessionOf($r1['refresh']));
        $this->me($r2['token'])->assertUnauthorized();
    }

    public function test_logging_out_one_phone_leaves_the_other_phone_intact(): void
    {
        $a = $this->login();
        $this->registerDevice($a['token'], 'phone-a');
        $b = $this->login();
        $this->registerDevice($b['token'], 'phone-b');

        $this->revokeToken($a['refresh'])->assertNoContent();

        $this->assertSame(['phone-b'], app(DeviceTokenService::class)->tokensFor($this->user));
        $this->me($b['token'])->assertOk();
        $this->refresh($b['refresh'])->assertOk();
    }

    public function test_bearer_logout_with_a_session_token_removes_everything(): void
    {
        $pair = $this->login();
        $this->registerDevice($pair['token'], 'fcm-1');

        $this->http($pair['token'])->postJson('/api/auth/logout')->assertNoContent();

        $this->assertSame(0, MobileSession::query()->count());
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertSame(0, DeviceToken::query()->count());
        $this->refresh($pair['refresh'])->assertUnauthorized();
    }

    public function test_bearer_logout_with_an_mcp_token_only_deletes_that_token(): void
    {
        $pair = $this->login();
        $mcp = $this->user->createToken('claude', ['mcp'])->plainTextToken;

        $this->http($mcp)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertSame(1, MobileSession::query()->count());
        $this->assertSame(1, PersonalAccessToken::query()->count());
        $this->me($pair['token'])->assertOk();
    }

    /** @param array{token: string, refresh: string} $pair */
    private function assertAccessLost(array $pair): void
    {
        $this->refresh($pair['refresh'])->assertUnauthorized();

        $this->assertNull($this->sessionOf($pair['refresh']));
        $this->assertSame(0, DeviceToken::query()->count());
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertSame(1, $this->revokedAudits('access_lost'));
    }

    public function test_refresh_kills_the_session_when_the_user_was_deactivated(): void
    {
        $pair = $this->login();
        $this->registerDevice($pair['token'], 'fcm-1');
        $this->user->forceFill(['is_active' => false])->save();

        $this->assertAccessLost($pair);
    }

    public function test_refresh_kills_the_session_when_the_last_membership_was_deactivated(): void
    {
        $pair = $this->login();
        $this->registerDevice($pair['token'], 'fcm-1');
        $this->membershipOf($this->user, $this->firm)->update(['is_active' => false]);

        $this->assertAccessLost($pair);
    }

    public function test_refresh_kills_the_session_when_mobile_access_was_removed_everywhere(): void
    {
        $pair = $this->login();
        $this->registerDevice($pair['token'], 'fcm-1');
        $this->removeMobileAccess($this->firm);

        $this->assertAccessLost($pair);
    }

    public function test_refresh_succeeds_while_another_firm_still_grants_mobile_access(): void
    {
        $second = $this->firm('Second');
        $this->member($second, 'Admin', $this->user);
        $this->consoleContext();
        $pair = $this->login();
        $this->removeMobileAccess($this->firm);

        $this->refresh($pair['refresh'])->assertOk();
        $this->assertSame(0, $this->revokedAudits('access_lost'));
    }

    private function removeMobileAccess(Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        Role::inTenant($tenant->id)->where('name', 'Admin')->firstOrFail()->revokePermissionTo(PermissionEnum::AccessMobileApp->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->consoleContext();
    }

    /** @return array{0: array{token: string, refresh: string}, 1: array{token: string, refresh: string}} */
    private function twoPhones(): array
    {
        $a = $this->login();
        $this->registerDevice($a['token'], 'phone-a');
        $b = $this->login();
        $this->registerDevice($b['token'], 'phone-b');

        return [$a, $b];
    }

    public function test_api_password_change_keeps_the_phone_it_was_made_on_and_revokes_the_others(): void
    {
        [$a, $b] = $this->twoPhones();
        $mcp = $this->user->createToken('claude', ['mcp']);

        $this->http($a['token'])->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertNoContent();

        $this->me($a['token'])->assertOk();
        $this->refresh($a['refresh'])->assertOk();
        $this->refresh($b['refresh'])->assertUnauthorized();
        $this->me($b['token'])->assertUnauthorized();
        $this->assertSame(['phone-a'], DeviceToken::query()->pluck('token')->all());
        $this->assertSame(1, $this->revokedAudits('password_changed'));
        $this->assertNotNull(PersonalAccessToken::findToken($mcp->plainTextToken));
    }

    public function test_a_wrong_current_password_revokes_nothing(): void
    {
        [$a, $b] = $this->twoPhones();

        $this->http($a['token'])->putJson('/api/profile/password', [
            'current_password' => 'nope',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertUnprocessable();

        $this->assertSame(2, MobileSession::query()->count());
        $this->assertSame(2, DeviceToken::query()->count());
        $this->refresh($b['refresh'])->assertOk();
    }

    public function test_web_password_change_logs_every_phone_out(): void
    {
        [$a, $b] = $this->twoPhones();
        $mcp = $this->user->createToken('claude', ['mcp']);

        $this->http()->actingAs($this->user)->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, MobileSession::query()->count());
        $this->assertSame(0, DeviceToken::query()->count());
        $this->refresh($a['refresh'])->assertUnauthorized();
        $this->me($b['token'])->assertUnauthorized();
        $this->assertSame(2, $this->revokedAudits('password_changed'));
        $this->assertNotNull(PersonalAccessToken::findToken($mcp->plainTextToken));
    }

    public function test_password_reset_logs_every_phone_out(): void
    {
        [$a, $b] = $this->twoPhones();
        $mcp = $this->user->createToken('claude', ['mcp']);
        $token = Password::createToken($this->user);

        $this->http()->post('/reset-password', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, MobileSession::query()->count());
        $this->assertSame(0, DeviceToken::query()->count());
        $this->refresh($b['refresh'])->assertUnauthorized();
        $this->me($a['token'])->assertUnauthorized();
        $this->assertSame(2, $this->revokedAudits('password_reset'));
        $this->assertNotNull(PersonalAccessToken::findToken($mcp->plainTextToken));
    }

    public function test_a_failed_reset_revokes_nothing(): void
    {
        $this->twoPhones();

        $this->http()->post('/reset-password', [
            'token' => 'invalid',
            'email' => $this->user->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('email');

        $this->assertSame(2, MobileSession::query()->count());
    }

    public function test_a_login_that_rehashes_the_password_revokes_no_session(): void
    {
        $this->twoPhones();
        $before = $this->user->fresh()?->password;
        /** @var BcryptHasher $hasher */
        $hasher = Hash::driver('bcrypt');
        $hasher->setRounds(6);

        $this->login();

        $this->assertNotSame($before, $this->user->fresh()?->password);
        $this->assertSame(3, MobileSession::query()->count());
        $this->assertSame(0, $this->revokedAudits('password_changed'));
    }

    public function test_the_first_use_of_an_access_token_confirms_the_rotation_with_one_write(): void
    {
        $pair = $this->login();
        $renewed = $this->renewed($pair['refresh']);
        $session = $this->sessionOf($renewed['refresh_token']);
        $this->assertNull($session?->rotation_confirmed_at);

        $writes = 0;
        DB::listen(function ($query) use (&$writes): void {
            if (str_starts_with($query->sql, 'update "mobile_sessions"')) {
                $writes++;
            }
        });

        $this->me($renewed['token'])->assertOk();
        $this->me($renewed['token'])->assertOk();
        $this->me($renewed['token'])->assertOk();

        $this->assertSame(1, $writes);
        $this->assertNotNull($this->sessionOf($renewed['refresh_token'])?->rotation_confirmed_at);
    }

    public function test_using_an_mcp_token_does_not_confirm_any_session(): void
    {
        $pair = $this->login();
        $renewed = $this->renewed($pair['refresh']);
        $mcp = $this->user->createToken('claude', ['mcp'])->plainTextToken;

        $this->http($mcp)->getJson('/api/me');

        $this->assertNull($this->sessionOf($renewed['refresh_token'])?->rotation_confirmed_at);
    }

    public function test_rotation_keeps_the_device_row_and_its_session(): void
    {
        $pair = $this->login();
        $this->registerDevice($pair['token'], 'fcm-1');
        $sessionId = explode('.', $pair['refresh'])[0];

        $renewed = $this->renewed($pair['refresh']);

        $device = DeviceToken::query()->sole();
        $this->assertSame($sessionId, $device->mobile_session_id);
        $this->registerDevice($renewed['token'], 'fcm-1');
        $this->assertSame(1, DeviceToken::query()->count());
    }

    public function test_the_daily_token_prune_keeps_session_and_device_and_refresh_still_works(): void
    {
        $pair = $this->login();
        $this->registerDevice($pair['token'], 'fcm-1');

        $this->travel(26)->hours();
        $this->artisanCommand('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();

        $this->assertSame(0, PersonalAccessToken::query()->count());
        $session = $this->sessionOf($pair['refresh']);
        $this->assertNotNull($session);
        $this->assertNull($session->personal_access_token_id);
        $this->assertSame(1, DeviceToken::query()->count());
        $this->refresh($pair['refresh'])->assertOk();
    }

    public function test_a_rotated_fcm_token_replaces_the_old_row_of_the_same_session(): void
    {
        $pair = $this->login();
        $this->registerDevice($pair['token'], 'old-fcm');
        $this->registerDevice($pair['token'], 'new-fcm');
        $this->registerDevice($pair['token'], 'new-fcm');

        $this->assertSame(['new-fcm'], DeviceToken::query()->pluck('token')->all());
    }

    public function test_a_token_seen_on_another_account_moves_to_the_new_user_and_session(): void
    {
        $other = $this->member($this->firm, 'Admin');
        $this->consoleContext();
        $theirs = $this->login($other);
        $this->registerDevice($theirs['token'], 'shared');

        $mine = $this->login();
        $this->registerDevice($mine['token'], 'shared');

        $device = DeviceToken::query()->sole();
        $this->assertSame($this->user->id, $device->user_id);
        $this->assertSame(explode('.', $mine['refresh'])[0], $device->mobile_session_id);
    }

    public function test_two_sessions_of_one_user_each_keep_a_row(): void
    {
        $this->twoPhones();

        $this->assertEqualsCanonicalizing(['phone-a', 'phone-b'], app(DeviceTokenService::class)->tokensFor($this->user));
    }

    public function test_the_migration_drops_legacy_mobile_tokens_and_device_rows_but_keeps_mcp_tokens(): void
    {
        /** @var Migration $migration */
        $migration = require base_path('database/migrations/2026_11_08_000100_create_mobile_sessions_table.php');
        assert(method_exists($migration, 'down'));
        $migration->down();

        $legacy = $this->user->createToken('mobile', ['mobile']);
        $mcp = $this->user->createToken('claude', ['mcp']);
        DB::table('device_tokens')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'personal_access_token_id' => $legacy->accessToken->getKey(),
            'token' => 'legacy-fcm',
            'platform' => 'ios',
            'last_seen_at' => now(),
        ]);

        assert(method_exists($migration, 'up'));
        $migration->up();

        $this->assertNull(PersonalAccessToken::findToken($legacy->plainTextToken));
        $this->assertNotNull(PersonalAccessToken::findToken($mcp->plainTextToken));
        $this->assertSame(0, DB::table('device_tokens')->count());
    }
}
