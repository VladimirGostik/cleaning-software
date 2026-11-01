<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Data\UpdateUserData;
use App\Models\DeviceToken;
use App\Models\MobileSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmployeeAccessService;
use App\Services\EmployeeService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class DeviceCleanupTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $user;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->user = $this->member($this->alpha, 'Admin');
        $this->actor = $this->member($this->alpha, 'Admin');
        $this->phone($this->user);
        DeviceToken::factory()->for($this->user)->create();
        $this->bindTenant($this->alpha);
    }

    private function phone(User $user): void
    {
        $session = MobileSession::factory()->for($user)->create();
        $token = $user->createToken('mobile', ['mobile']);
        $session->update(['personal_access_token_id' => $token->accessToken->getKey()]);
        DeviceToken::factory()->for($user)->create(['mobile_session_id' => $session->id]);
    }

    private function assertEverythingGone(): void
    {
        $this->assertSame(0, DeviceToken::query()->count());
        $this->assertSame(0, MobileSession::query()->count());
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    private function assertEverythingKept(): void
    {
        $this->assertSame(2, DeviceToken::query()->count());
        $this->assertSame(1, MobileSession::query()->count());
        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    private function giveSecondMembership(): void
    {
        $this->member($this->beta, 'Admin', $this->user);
        $this->bindTenant($this->alpha);
    }

    public function test_deactivating_the_last_active_membership_removes_sessions_and_devices(): void
    {
        app(EmployeeService::class)->deactivate($this->membershipOf($this->user, $this->alpha));

        $this->assertEverythingGone();
    }

    public function test_deactivating_one_of_two_memberships_keeps_everything(): void
    {
        $this->giveSecondMembership();

        app(EmployeeService::class)->deactivate($this->membershipOf($this->user, $this->alpha));

        $this->assertEverythingKept();
    }

    public function test_revoking_employee_access_removes_everything_when_no_membership_is_left(): void
    {
        app(EmployeeAccessService::class)->revoke($this->membershipOf($this->user, $this->alpha), $this->actor);

        $this->assertEverythingGone();
    }

    public function test_revoking_employee_access_keeps_everything_with_another_active_membership(): void
    {
        $this->giveSecondMembership();

        app(EmployeeAccessService::class)->revoke($this->membershipOf($this->user, $this->alpha), $this->actor);

        $this->assertEverythingKept();
    }

    public function test_user_delete_removes_everything_only_when_no_membership_is_left(): void
    {
        $this->giveSecondMembership();

        app(UserService::class)->delete($this->user);
        $this->assertEverythingKept();

        $this->bindTenant($this->beta);
        app(UserService::class)->delete($this->user);
        $this->assertEverythingGone();
    }

    public function test_deactivating_a_user_in_the_users_module_removes_everything_when_no_membership_is_left(): void
    {
        app(UserService::class)->update($this->user, new UpdateUserData(name: $this->user->name, email: $this->user->email, is_active: false, roles: ['Admin']), $this->actor);

        $this->assertEverythingGone();
    }

    public function test_deactivating_a_user_in_the_users_module_keeps_everything_with_another_active_membership(): void
    {
        $this->giveSecondMembership();

        app(UserService::class)->update($this->user, new UpdateUserData(name: $this->user->name, email: $this->user->email, is_active: false, roles: ['Admin']), $this->actor);

        $this->assertEverythingKept();
    }

    public function test_a_session_less_device_is_removed_with_the_last_membership(): void
    {
        MobileSession::query()->delete();
        PersonalAccessToken::query()->delete();

        app(EmployeeService::class)->deactivate($this->membershipOf($this->user, $this->alpha));

        $this->assertSame(0, DeviceToken::query()->count());
    }
}
