<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\PermissionEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class MeTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // happy: authed user with active tenant → 200 with correct (shrunk) shape
    // -------------------------------------------------------------------------

    public function test_returns_200_with_exact_shape_for_authenticated_user_with_active_tenant(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);

        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertOk();

        $response->assertJsonStructure([
            'userId',
            'activeTenantId',
            'permissions',
        ]);

        $response->assertJsonMissingPath('features');
        $response->assertJsonMissingPath('accountPlan');
        $response->assertJsonMissingPath('remainingTenantSlots');

        $response->assertJsonPath('activeTenantId', $tenant->id);

        // Permissions must be non-empty (Účtovníčka has finance perms)
        $returnedPermissions = $response->json('permissions');
        $this->assertNotEmpty($returnedPermissions);

        // Must contain a known Účtovníčka permission and not contain one she lacks
        $this->assertContains(PermissionEnum::ViewInvoices->value, $returnedPermissions);
        $this->assertNotContains(PermissionEnum::ManageRoles->value, $returnedPermissions);
    }

    // -------------------------------------------------------------------------
    // happy: permissions reflect active-team Spatie scope
    // -------------------------------------------------------------------------

    public function test_permissions_reflect_active_tenant_role_assignments(): void
    {
        // Arrange — Admin (all permissions)
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Admin', $tenant);

        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertOk();
        $returnedPermissions = $response->json('permissions');

        foreach (PermissionEnum::cases() as $case) {
            $this->assertContains(
                $case->value,
                $returnedPermissions,
                "Admin should have permission: {$case->value}",
            );
        }
    }

    // -------------------------------------------------------------------------
    // happy: breadth-modifier permission surfaces for a management role
    // -------------------------------------------------------------------------

    public function test_permissions_include_view_all_schedule_for_veduca(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Vedúca', $tenant);

        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertOk();
        $this->assertContains(PermissionEnum::ViewAllSchedule->value, $response->json('permissions'));
    }

    // -------------------------------------------------------------------------
    // failure: unauthenticated → 401
    // -------------------------------------------------------------------------

    public function test_returns_401_for_unauthenticated_request(): void
    {
        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // edge: user with no active tenant → activeTenantId null, permissions empty, still 200
    // -------------------------------------------------------------------------

    public function test_returns_null_active_tenant_and_empty_permissions_when_no_active_tenant(): void
    {
        // Arrange — authenticated user with no tenant context bound at all.
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertOk();
        $response->assertJsonPath('activeTenantId', null);
        $response->assertJsonPath('permissions', []);
    }

    // -------------------------------------------------------------------------
    // edge: cross-tenant isolation — permissions reflect ONLY active tenant scope
    // -------------------------------------------------------------------------

    public function test_permissions_reflect_only_active_tenant_not_second_tenant(): void
    {
        // Arrange — same user, member of two tenants with different roles
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $tenantA = Tenant::factory()->forOwner($ownerA)->create();
        $tenantB = Tenant::factory()->forOwner($ownerB)->create();

        // Seed permissions first (shared global set)
        $this->seed(PermissionSeeder::class);

        // Seed role templates for both tenants
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->id);
        $this->seed(RoleTemplatesSeeder::class);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        $this->seed(RoleTemplatesSeeder::class);

        $user = User::factory()->create(['is_active' => true]);

        // Tenant A: Admin (all permissions)
        TenantMembership::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantA->id,
            'is_active' => true,
            'joined_at' => now()->subMinute(),
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->id);
        /** @var Role $ownerRoleA */
        $ownerRoleA = Role::where('name', 'Admin')->where('tenant_id', $tenantA->id)->firstOrFail();
        $user->assignRole($ownerRoleA);

        // Tenant B: Interná upratovačka (view schedule only)
        TenantMembership::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantB->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        /** @var Role $cleanerRoleB */
        $cleanerRoleB = Role::where('name', 'Interná upratovačka')->where('tenant_id', $tenantB->id)->firstOrFail();
        $user->assignRole($cleanerRoleB);

        // Active tenant = B (Interná upratovačka scope)
        $this->actingAs($user);
        session(['active_tenant_id' => $tenantB->id]);
        app()->instance('current_tenant_id', $tenantB->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);

        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertOk();
        $response->assertJsonPath('activeTenantId', $tenantB->id);

        $returnedPermissions = $response->json('permissions');

        // Must contain Interná upratovačka permission
        $this->assertContains(PermissionEnum::ViewSchedule->value, $returnedPermissions);

        // Must NOT contain Admin-only permissions from Tenant A
        $this->assertNotContains(PermissionEnum::ManageRoles->value, $returnedPermissions);
        $this->assertNotContains(PermissionEnum::DeleteClients->value, $returnedPermissions);
    }
}
