<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class PermissionTeamScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_user_has_different_roles_per_tenant(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantA->id, 'is_active' => true, 'joined_at' => now()]);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantB->id, 'is_active' => true, 'joined_at' => now()]);

        Permission::firstOrCreate(['name' => 'view clients', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->id);
        $roleA = Role::create(['name' => 'client-viewer', 'guard_name' => 'web', 'tenant_id' => $tenantA->id]);
        $roleA->syncPermissions(['view clients']);
        $user->assignRole($roleA);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);

        $this->assertFalse($user->can('view clients'));

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($user->fresh()->can('view clients'));
    }

    public function test_can_flips_when_permissions_team_id_changes(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantA->id, 'is_active' => true, 'joined_at' => now()]);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenantB->id, 'is_active' => true, 'joined_at' => now()]);

        Permission::firstOrCreate(['name' => 'view roles', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        $roleB = Role::create(['name' => 'role-viewer', 'guard_name' => 'web', 'tenant_id' => $tenantB->id]);
        $roleB->syncPermissions(['view roles']);
        $user->assignRole($roleB);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->id);
        $this->assertFalse($user->fresh()->can('view roles'));

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        $this->assertTrue($user->fresh()->can('view roles'));
    }

    public function test_role_in_tenant_scope_filters_by_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $roleA = Role::create(['name' => 'shared-name', 'guard_name' => 'web', 'tenant_id' => $tenantA->id]);
        $roleB = Role::create(['name' => 'shared-name', 'guard_name' => 'web', 'tenant_id' => $tenantB->id]);

        $found = Role::inTenant($tenantA->id)->where('name', 'shared-name')->first();

        $this->assertSame($roleA->id, $found->id);
        $this->assertNotSame($roleB->id, $found->id);
    }
}
