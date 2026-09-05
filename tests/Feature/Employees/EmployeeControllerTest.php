<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // index — accessible to authorized user (feature gate removed, see
    // .claude/plans/remove-entitlement-layer.md — RBAC is the only remaining gate)
    // -------------------------------------------------------------------------

    public function test_index_accessible_to_authorized_user(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $this->get(route('employees.index'))->assertOk();
    }

    // -------------------------------------------------------------------------
    // index — permission gate
    // -------------------------------------------------------------------------

    public function test_index_forbidden_for_upratovacka(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $this->get(route('employees.index'))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // store — happy path
    // -------------------------------------------------------------------------

    public function test_store_creates_new_employee_and_redirects(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->post(route('employees.store'), [
            'email' => 'hire@example.com',
            'first_name' => 'Lucia',
            'last_name' => 'Kovacova',
            'phone' => null,
            'role_name' => 'Interná upratovačka',
            'permissions' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'hire@example.com']);
        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'first_name' => 'Lucia',
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // store — fails when not create permission
    // -------------------------------------------------------------------------

    public function test_store_forbidden_for_upratovacka(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $this->post(route('employees.store'), [
            'email' => 'fail@example.com',
            'first_name' => null,
            'last_name' => null,
            'phone' => null,
            'role_name' => 'Interná upratovačka',
            'permissions' => [],
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // deactivate — happy path
    // -------------------------------------------------------------------------

    public function test_deactivate_sets_membership_inactive(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $targetUser = User::factory()->create();
        $membership = TenantMembership::create([
            'user_id' => $targetUser->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->post(route('employees.deactivate', $membership->id))
            ->assertRedirect(route('employees.index'));

        $this->assertFalse($membership->refresh()->is_active);
    }

    // -------------------------------------------------------------------------
    // show — tenant isolation
    // -------------------------------------------------------------------------

    public function test_show_denied_for_membership_in_other_tenant(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        // Create a membership in a completely different tenant.
        $otherOwner = User::factory()->create();
        $otherTenant = Tenant::factory()->forOwner($otherOwner)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($otherTenant->id);
        $this->seed(PermissionSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($otherTenant->id);
        $this->seed(RoleTemplatesSeeder::class);

        $otherMembership = TenantMembership::create([
            'user_id' => $otherOwner->id,
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // Re-set current tenant context back to the original user's tenant.
        $myTenant = Tenant::where('owner_id', $user->id)->first();
        app()->instance('current_tenant_id', $myTenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($myTenant->id);
        session(['active_tenant_id' => $myTenant->id]);

        $this->get(route('employees.show', $otherMembership->id))
            ->assertForbidden();
    }
}
