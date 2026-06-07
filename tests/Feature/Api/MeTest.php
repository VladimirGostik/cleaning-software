<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\PermissionEnum;
use App\Enums\SubscriptionPlanEnum;
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
    // happy: authed user with active tenant → 200 with correct shape
    // -------------------------------------------------------------------------

    public function test_returns_200_with_permissions_and_features_for_authenticated_user_with_active_tenant(): void
    {
        // Arrange — Účtovníčka on a Pro-owned tenant
        $owner = User::factory()->pro()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);

        $expectedFeatures = config('subscription.plans.' . SubscriptionPlanEnum::Pro->value . '.features');

        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertOk();

        $response->assertJsonStructure([
            'userId',
            'activeTenantId',
            'permissions',
            'features',
            'accountPlan',
            'remainingTenantSlots',
        ]);

        $response->assertJsonPath('activeTenantId', $tenant->id);

        $returnedFeatures = $response->json('features');
        sort($returnedFeatures);
        sort($expectedFeatures);
        $this->assertSame($expectedFeatures, $returnedFeatures);

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
        // Arrange — Vlastník (all permissions) on a Pro-owned tenant
        $owner = User::factory()->pro()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Vlastník', $tenant);

        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertOk();
        $returnedPermissions = $response->json('permissions');

        foreach (PermissionEnum::cases() as $case) {
            $this->assertContains(
                $case->value,
                $returnedPermissions,
                "Vlastník should have permission: {$case->value}",
            );
        }
    }

    // -------------------------------------------------------------------------
    // happy: accountPlan + remainingTenantSlots in payload
    // -------------------------------------------------------------------------

    public function test_returns_account_plan_and_remaining_slots_for_pro_user(): void
    {
        // Arrange — Pro user owns 1 tenant; Pro limit = 3 → remaining = 2
        $owner = User::factory()->pro()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        $this->actingAs($owner);
        session(['active_tenant_id' => $tenant->id]);
        app()->instance('current_tenant_id', $tenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertOk();
        $response->assertJsonPath('accountPlan', 'pro');
        $response->assertJsonPath('remainingTenantSlots', 2);
    }

    public function test_returns_null_remaining_slots_for_enterprise_user(): void
    {
        // Arrange — Enterprise user = unlimited
        $owner = User::factory()->enterprise()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();

        $this->actingAs($owner);
        session(['active_tenant_id' => $tenant->id]);
        app()->instance('current_tenant_id', $tenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertOk();
        $response->assertJsonPath('accountPlan', 'enterprise');
        $response->assertJsonPath('remainingTenantSlots', null);
    }

    public function test_returns_zero_remaining_slots_for_free_user_at_limit(): void
    {
        // Arrange — Free user owns 1 tenant (limit 1) → remaining = 0
        $owner = User::factory()->create(); // default Free
        $tenant = Tenant::factory()->forOwner($owner)->create();

        $this->actingAs($owner);
        session(['active_tenant_id' => $tenant->id]);
        app()->instance('current_tenant_id', $tenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertOk();
        $response->assertJsonPath('accountPlan', 'free');
        $response->assertJsonPath('remainingTenantSlots', 0);
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
    // edge: Free plan tenant → features is empty array
    // -------------------------------------------------------------------------

    public function test_features_is_empty_array_for_free_plan_owner_tenant(): void
    {
        // Arrange — default User = Free plan; tenant owned by that user
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Upratovačka', $tenant);

        // Act
        $response = $this->getJson(route('api.me'));

        // Assert
        $response->assertOk();
        $response->assertJsonPath('features', []);

        // Upratovačka still has a permission (view schedule) even on Free plan
        $returnedPermissions = $response->json('permissions');
        $this->assertNotEmpty($returnedPermissions);
        $this->assertContains(PermissionEnum::ViewSchedule->value, $returnedPermissions);
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

        // Tenant A: Vlastník (all permissions)
        TenantMembership::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantA->id,
            'is_active' => true,
            'joined_at' => now()->subMinute(),
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->id);
        /** @var Role $ownerRoleA */
        $ownerRoleA = Role::where('name', 'Vlastník')->where('tenant_id', $tenantA->id)->firstOrFail();
        $user->assignRole($ownerRoleA);

        // Tenant B: Upratovačka (view schedule only)
        TenantMembership::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantB->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        /** @var Role $cleanerRoleB */
        $cleanerRoleB = Role::where('name', 'Upratovačka')->where('tenant_id', $tenantB->id)->firstOrFail();
        $user->assignRole($cleanerRoleB);

        // Active tenant = B (Upratovačka scope)
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

        // Must contain Upratovačka permission
        $this->assertContains(PermissionEnum::ViewSchedule->value, $returnedPermissions);

        // Must NOT contain Vlastník-only permissions from Tenant A
        $this->assertNotContains(PermissionEnum::ManageRoles->value, $returnedPermissions);
        $this->assertNotContains(PermissionEnum::DeleteClients->value, $returnedPermissions);
    }
}
