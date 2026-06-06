<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\FeatureEnum;
use App\Enums\PermissionEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class SuperAdminBypassTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // happy: super-admin passes a policy check with no explicit permission
    // -------------------------------------------------------------------------

    public function test_super_admin_can_passes_client_policy_delete_without_explicit_permission(): void
    {
        // Arrange — super-admin with no role assignment on any tenant
        $superAdmin = User::factory()->superAdmin()->create();
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // No permission seeding, no role assignment — bare super-admin user
        $this->actingAs($superAdmin);
        app()->instance('current_tenant_id', $tenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // Act
        $allowed = $superAdmin->can('delete', $client);

        // Assert
        $this->assertTrue($allowed, 'Gate::before must grant super-admin access via ClientPolicy::delete');
    }

    public function test_super_admin_gate_allows_returns_true_for_arbitrary_ability(): void
    {
        // Arrange
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        // Act — use Gate::allows directly for a permission they have no DB entry for
        $allowed = Gate::forUser($superAdmin)->allows(PermissionEnum::ManageSubscription->value);

        // Assert
        $this->assertTrue($allowed, 'Gate::before must return true for super-admin on any ability');
    }

    public function test_super_admin_bypass_applies_cross_tenant(): void
    {
        // Arrange — super-admin, active context set to tenantA, client on tenantB
        $superAdmin = User::factory()->superAdmin()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $clientOnTenantB = Client::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($superAdmin);
        app()->instance('current_tenant_id', $tenantA->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->id);

        // Act — attempting to edit a record belonging to a different tenant
        $allowed = $superAdmin->can('update', $clientOnTenantB);

        // Assert
        $this->assertTrue($allowed, 'Gate::before bypass must apply cross-tenant for super-admin');
    }

    // -------------------------------------------------------------------------
    // failure: super-admin is still blocked by feature: middleware
    // -------------------------------------------------------------------------

    public function test_super_admin_gets_403_on_feature_gated_route_when_tenant_plan_lacks_feature(): void
    {
        // Arrange — Free plan has no features; register a throwaway route gated by 'quotes'
        $tenant = Tenant::factory()->create(['subscription_plan' => SubscriptionPlanEnum::Free->value]);

        Route::get('/_test/super-admin-quotes', fn () => response('ok', 200))
            ->middleware(['web', 'feature:' . FeatureEnum::Quotes->value])
            ->name('test.super_admin.quotes');

        $superAdmin = User::factory()->superAdmin()->create();

        TenantMembership::create([
            'user_id' => $superAdmin->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->actingAs($superAdmin);
        session(['active_tenant_id' => $tenant->id]);
        app()->instance('current_tenant_id', $tenant->id);

        // Act
        $response = $this->get('/_test/super-admin-quotes');

        // Assert — plan axis must block even super-admin
        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // normal user: Gate::before returns null → falls through to real checks
    // -------------------------------------------------------------------------

    public function test_normal_user_without_permission_is_denied_by_policy(): void
    {
        // Arrange — plain user, no roles, no permissions
        $user = User::factory()->create(['is_super_admin' => false]);
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user);
        app()->instance('current_tenant_id', $tenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // Act
        $allowed = $user->can('delete', $client);

        // Assert — no bypass, no permission → denied
        $this->assertFalse($allowed, 'Normal user without delete clients permission must be denied');
    }

    public function test_normal_user_with_permission_is_allowed_by_policy(): void
    {
        // Arrange — give the user the 'delete clients' permission explicitly
        $user = User::factory()->create(['is_super_admin' => false]);
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->seed(PermissionSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->givePermissionTo(PermissionEnum::DeleteClients->value);

        $this->actingAs($user);
        app()->instance('current_tenant_id', $tenant->id);

        // Act
        $allowed = $user->can('delete', $client);

        // Assert
        $this->assertTrue($allowed, 'Normal user with delete clients permission must be allowed');
    }

    public function test_gate_before_returns_null_for_normal_user_so_policies_run(): void
    {
        // Arrange
        $user = User::factory()->create(['is_super_admin' => false]);

        // Act — inspect what Gate::before closure returns by checking can() on a
        // permission the user does NOT have (if Gate::before returned true it would pass)
        $allowed = Gate::forUser($user)->allows(PermissionEnum::ManageSubscription->value);

        // Assert — no bypass, no permission → Gate falls through to policy/permission check
        $this->assertFalse($allowed);
    }
}
