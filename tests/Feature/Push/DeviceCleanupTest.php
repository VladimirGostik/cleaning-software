<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class DeviceCleanupTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->user = $this->member($this->alpha, 'Admin');
        DeviceToken::factory()->for($this->user)->create();
        $this->bindTenant($this->alpha);
    }

    public function test_deactivating_the_last_active_membership_deletes_devices(): void
    {
        app(EmployeeService::class)->deactivate($this->membershipOf($this->user, $this->alpha));

        $this->assertSame(0, DeviceToken::query()->count());
    }

    public function test_deactivating_one_of_two_memberships_keeps_devices(): void
    {
        $this->member($this->beta, 'Admin', $this->user);
        $this->bindTenant($this->alpha);

        app(EmployeeService::class)->deactivate($this->membershipOf($this->user, $this->alpha));

        $this->assertSame(1, DeviceToken::query()->count());
    }

    public function test_user_delete_removes_devices_only_when_no_membership_is_left(): void
    {
        $this->member($this->beta, 'Admin', $this->user);
        $this->bindTenant($this->alpha);

        app(UserService::class)->delete($this->user);
        $this->assertSame(1, DeviceToken::query()->count());

        $this->bindTenant($this->beta);
        app(UserService::class)->delete($this->user);
        $this->assertSame(0, DeviceToken::query()->count());
    }
}
