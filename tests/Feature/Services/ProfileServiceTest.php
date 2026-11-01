<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Data\UpdateProfileData;
use App\Models\MobileSession;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProfileService::class);
    }

    public function test_update_profile_changes_user_attributes_in_database(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com', 'locale' => 'sk']);
        $data = new UpdateProfileData(name: 'New Name', email: 'new@example.com', locale: 'en');

        $this->service->updateProfile($user, $data);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
            'locale' => 'en',
        ]);
    }

    public function test_update_profile_sets_app_locale(): void
    {
        $user = User::factory()->create(['locale' => 'sk']);
        $data = new UpdateProfileData(name: $user->name, email: $user->email, locale: 'en');

        $this->service->updateProfile($user, $data);

        $this->assertEquals('en', app()->getLocale());
    }

    public function test_update_profile_sets_session_locale(): void
    {
        $user = User::factory()->create(['locale' => 'sk']);
        $data = new UpdateProfileData(name: $user->name, email: $user->email, locale: 'en');

        $this->service->updateProfile($user, $data);

        $this->assertEquals('en', session('locale'));
    }

    public function test_update_profile_returns_fresh_user(): void
    {
        $user = User::factory()->create(['name' => 'Before']);
        $data = new UpdateProfileData(name: 'After', email: $user->email, locale: 'sk');

        $result = $this->service->updateProfile($user, $data);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('After', $result->name);
    }

    public function test_change_password_updates_the_hash(): void
    {
        $user = User::factory()->create();

        $this->service->changePassword($user, 'brand-new-pass');

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password ?? ''));
    }

    public function test_change_password_without_a_current_session_revokes_every_session(): void
    {
        $user = User::factory()->create();
        MobileSession::factory()->count(2)->for($user)->create();
        $bystander = MobileSession::factory()->create();

        $this->service->changePassword($user, 'brand-new-pass');

        $this->assertSame(0, MobileSession::query()->where('user_id', $user->id)->count());
        $this->assertTrue(MobileSession::query()->whereKey($bystander->id)->exists());
    }

    public function test_change_password_keeps_the_session_behind_the_current_token(): void
    {
        $user = User::factory()->create();
        $keep = MobileSession::factory()->for($user)->create();
        $drop = MobileSession::factory()->for($user)->create();
        $token = $user->createToken('mobile', ['mobile']);
        $keep->update(['personal_access_token_id' => $token->accessToken->getKey()]);
        $user->withAccessToken($token->accessToken);

        $this->service->changePassword($user, 'brand-new-pass');

        $this->assertTrue(MobileSession::query()->whereKey($keep->id)->exists());
        $this->assertFalse(MobileSession::query()->whereKey($drop->id)->exists());
    }
}
