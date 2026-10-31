<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Enums\DevicePlatformEnum;
use App\Models\DeviceToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class DeviceRegistrationTest extends TestCase
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

    private function asMobile(): void
    {
        $this->consoleContext();
        $plain = $this->user->createToken('mobile', ['mobile'])->plainTextToken;
        $this->withToken($plain)->withHeaders(['X-Tenant-Id' => $this->firm->id]);
    }

    public function test_register_creates_a_row_for_each_platform(): void
    {
        $this->asMobile();

        $this->postJson(route('api.devices.store'), ['token' => 'ios-token', 'platform' => 'ios', 'app_version' => '1.2.0'])->assertNoContent();
        $this->postJson(route('api.devices.store'), ['token' => 'android-token', 'platform' => 'android'])->assertNoContent();

        $this->assertSame(DevicePlatformEnum::Ios, DeviceToken::query()->where('token', 'ios-token')->firstOrFail()->platform);
        $this->assertSame(DevicePlatformEnum::Android, DeviceToken::query()->where('token', 'android-token')->firstOrFail()->platform);
        $this->assertSame('1.2.0', DeviceToken::query()->where('token', 'ios-token')->firstOrFail()->app_version);
    }

    public function test_registering_the_same_token_twice_keeps_one_row(): void
    {
        $this->asMobile();

        $this->postJson(route('api.devices.store'), ['token' => 'same', 'platform' => 'ios', 'app_version' => '1.0.0'])->assertNoContent();
        $this->postJson(route('api.devices.store'), ['token' => 'same', 'platform' => 'ios', 'app_version' => '1.1.0'])->assertNoContent();

        $this->assertSame(1, DeviceToken::query()->count());
        $this->assertSame('1.1.0', DeviceToken::query()->firstOrFail()->app_version);
    }

    public function test_token_is_rehomed_to_the_current_user(): void
    {
        $other = $this->member($this->firm, 'Admin');
        DeviceToken::factory()->for($other)->create(['token' => 'shared']);
        $this->consoleContext();

        $this->asMobile();
        $this->postJson(route('api.devices.store'), ['token' => 'shared', 'platform' => 'android'])->assertNoContent();

        $this->assertSame(1, DeviceToken::query()->count());
        $this->assertSame($this->user->id, DeviceToken::query()->firstOrFail()->user_id);
    }

    public function test_platform_outside_ios_and_android_is_rejected(): void
    {
        $this->asMobile();

        $this->postJson(route('api.devices.store'), ['token' => 't', 'platform' => 'windows'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform');
        $this->assertSame(0, DeviceToken::query()->count());
    }

    public function test_token_is_required_and_capped_at_255_chars(): void
    {
        $this->asMobile();

        $this->postJson(route('api.devices.store'), ['platform' => 'ios'])->assertJsonValidationErrors('token');
        $this->postJson(route('api.devices.store'), ['token' => str_repeat('a', 256), 'platform' => 'ios'])->assertJsonValidationErrors('token');
        $this->postJson(route('api.devices.store'), ['token' => str_repeat('a', 255), 'platform' => 'ios'])->assertNoContent();
    }

    public function test_unregister_deletes_own_token(): void
    {
        DeviceToken::factory()->for($this->user)->create(['token' => 'mine']);
        $this->consoleContext();

        $this->asMobile();
        $this->deleteJson(route('api.devices.destroy'), ['token' => 'mine'])->assertNoContent();

        $this->assertSame(0, DeviceToken::query()->count());
    }

    public function test_unregister_never_deletes_another_users_token_and_unknown_token_is_noop(): void
    {
        $other = $this->member($this->firm, 'Admin');
        DeviceToken::factory()->for($other)->create(['token' => 'theirs']);
        $this->consoleContext();

        $this->asMobile();
        $this->deleteJson(route('api.devices.destroy'), ['token' => 'theirs'])->assertNoContent();
        $this->deleteJson(route('api.devices.destroy'), ['token' => 'unknown'])->assertNoContent();

        $this->assertSame(1, DeviceToken::query()->count());
    }

    public function test_endpoints_require_authentication_and_the_mobile_ability(): void
    {
        $this->postJson(route('api.devices.store'), ['token' => 't', 'platform' => 'ios'])->assertUnauthorized();
        $this->deleteJson(route('api.devices.destroy'), ['token' => 't'])->assertUnauthorized();
    }

    public function test_registration_is_throttled_to_ten_per_minute(): void
    {
        $this->asMobile();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('api.devices.store'), ['token' => "t{$i}", 'platform' => 'ios'])->assertNoContent();
        }

        $this->postJson(route('api.devices.store'), ['token' => 't11', 'platform' => 'ios'])->assertTooManyRequests();
    }

    public function test_unregister_requires_a_token_in_the_body(): void
    {
        $this->asMobile();

        $this->deleteJson(route('api.devices.destroy'))->assertJsonValidationErrors('token');
    }

    public function test_token_is_not_part_of_the_delete_url(): void
    {
        $this->assertSame('/api/devices', parse_url(route('api.devices.destroy'), PHP_URL_PATH));
    }

    public function test_register_stores_the_sanctum_token_and_revoking_it_drops_the_device(): void
    {
        $this->asMobile();

        $this->postJson(route('api.devices.store'), ['token' => 'bound', 'platform' => 'ios'])->assertNoContent();

        $device = DeviceToken::query()->firstOrFail();
        $this->assertSame($this->user->tokens()->firstOrFail()->id, $device->personal_access_token_id);

        $this->user->tokens()->delete();

        $this->assertSame(0, DeviceToken::query()->count());
    }

    public function test_logout_drops_the_device_bound_to_the_token(): void
    {
        $this->asMobile();
        $this->postJson(route('api.devices.store'), ['token' => 'bound', 'platform' => 'ios'])->assertNoContent();
        $this->assertSame(1, DeviceToken::query()->count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/logout')->assertNoContent();

        $this->assertSame(0, DeviceToken::query()->count());
    }
}
