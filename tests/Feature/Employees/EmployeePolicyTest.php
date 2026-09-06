<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Policies\TenantMembershipPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmployeePolicyTest extends TestCase
{
    use RefreshDatabase;

    private TenantMembershipPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TenantMembershipPolicy;
    }

    public function test_admin_can_do_everything_within_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $membership));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $membership));
        $this->assertTrue($this->policy->delete($user, $membership));
        $this->assertTrue($this->policy->reactivate($user, $membership));
        $this->assertTrue($this->policy->assignRole($user, $membership));
    }

    public function test_vedúca_can_view_and_assign_but_not_edit(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Vedúca', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $membership));
        $this->assertTrue($this->policy->assignRole($user, $membership));
        $this->assertFalse($this->policy->update($user, $membership));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->delete($user, $membership));
    }

    public function test_cross_tenant_membership_is_denied(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenantA);
        $foreignMembership = TenantMembership::factory()->create(['tenant_id' => $tenantB->id]);

        $this->assertFalse($this->policy->view($user, $foreignMembership));
        $this->assertFalse($this->policy->update($user, $foreignMembership));
        $this->assertFalse($this->policy->delete($user, $foreignMembership));
    }

    public function test_cannot_deactivate_self(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $ownMembership = TenantMembership::query()->where('user_id', $user->id)->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertFalse($this->policy->delete($user, $ownMembership));
    }

    public function test_cannot_deactivate_tenant_owner(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $tenant->owner()->firstOrFail();
        $admin = $this->actingAsTenantUser('Admin', $tenant);
        $ownerMembership = TenantMembership::create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->assertFalse($this->policy->delete($admin, $ownerMembership));
    }

    public function test_zákazník_cannot_view_or_manage_employees(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Zákazník', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, $membership));
        $this->assertFalse($this->policy->assignRole($user, $membership));
    }
}
