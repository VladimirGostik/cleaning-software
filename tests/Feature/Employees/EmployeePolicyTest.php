<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Enums\SubscriptionPlanEnum;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Policies\TenantMembershipPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmployeePolicyTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Vlastník — can do all
    // -------------------------------------------------------------------------

    public function test_vlastnik_can_view_any(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $this->assertTrue((new TenantMembershipPolicy)->viewAny($user));
    }

    public function test_vlastnik_can_create(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $this->assertTrue((new TenantMembershipPolicy)->create($user));
    }

    public function test_vlastnik_can_update_own_tenant_membership(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $membership = TenantMembership::where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertTrue((new TenantMembershipPolicy)->update($user, $membership));
    }

    public function test_vlastnik_can_delete(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $membership = TenantMembership::where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertTrue((new TenantMembershipPolicy)->delete($user, $membership));
    }

    // -------------------------------------------------------------------------
    // Vedúca — can view but not create/edit/delete
    // -------------------------------------------------------------------------

    public function test_veduci_can_view_any(): void
    {
        $user = $this->actingAsTenantUser('Vedúca');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $this->assertTrue((new TenantMembershipPolicy)->viewAny($user));
    }

    public function test_veduci_cannot_create(): void
    {
        $user = $this->actingAsTenantUser('Vedúca');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $this->assertFalse((new TenantMembershipPolicy)->create($user));
    }

    public function test_veduci_cannot_delete(): void
    {
        $user = $this->actingAsTenantUser('Vedúca');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();

        $otherUser = User::factory()->create();
        $membership = TenantMembership::create([
            'user_id' => $otherUser->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->assertFalse((new TenantMembershipPolicy)->delete($user, $membership));
    }

    // -------------------------------------------------------------------------
    // Upratovačka — cannot do anything
    // -------------------------------------------------------------------------

    public function test_upratovacka_cannot_view_any(): void
    {
        $user = $this->actingAsTenantUser('Upratovačka');

        $this->assertFalse((new TenantMembershipPolicy)->viewAny($user));
    }

    public function test_upratovacka_cannot_create(): void
    {
        $user = $this->actingAsTenantUser('Upratovačka');

        $this->assertFalse((new TenantMembershipPolicy)->create($user));
    }

    // -------------------------------------------------------------------------
    // Tenant isolation — cannot operate on other tenant's membership
    // -------------------------------------------------------------------------

    public function test_update_denied_for_other_tenant_membership(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Create a membership in a different tenant.
        $otherTenant = Tenant::factory()->create(['owner_id' => User::factory()->create()->id]);
        $otherUser = User::factory()->create();
        $otherMembership = TenantMembership::create([
            'user_id' => $otherUser->id,
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->assertFalse((new TenantMembershipPolicy)->update($user, $otherMembership));
    }
}
