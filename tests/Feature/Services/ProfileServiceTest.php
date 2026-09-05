<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Data\UpdateProfileData;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
