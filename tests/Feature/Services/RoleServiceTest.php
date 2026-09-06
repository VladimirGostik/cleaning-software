<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Data\PermissionGroupData;
use App\Enums\PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\RoleService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class RoleServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->service = app(RoleService::class);
    }

    private function bindFreshTenant(): Tenant
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        return $tenant;
    }

    public function test_create_persists_role_with_correct_guard(): void
    {
        $this->bindFreshTenant();

        $role = $this->service->create('editor');

        $this->assertDatabaseHas('roles', ['name' => 'editor', 'guard_name' => 'web']);
        $this->assertInstanceOf(Role::class, $role);
    }

    public function test_create_syncs_permissions(): void
    {
        $this->bindFreshTenant();
        Permission::firstOrCreate(['name' => 'view employees', 'guard_name' => 'web']);

        $role = $this->service->create('limited', ['view employees']);

        $this->assertTrue($role->hasPermissionTo('view employees'));
    }

    public function test_create_throws_when_role_name_already_exists_in_same_tenant(): void
    {
        $tenant = $this->bindFreshTenant();
        Role::create(['name' => 'duplicate', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/duplicate/');

        $this->service->create('duplicate');
    }

    public function test_create_with_same_name_in_another_tenant_does_not_throw(): void
    {
        $foreignTenant = Tenant::factory()->create();
        Role::create(['name' => 'shared', 'guard_name' => 'web', 'tenant_id' => $foreignTenant->id]);

        $this->bindFreshTenant();

        $role = $this->service->create('shared');

        $this->assertInstanceOf(Role::class, $role);
    }

    public function test_create_returns_role_with_permissions_loaded(): void
    {
        $this->bindFreshTenant();
        Permission::firstOrCreate(['name' => 'view roles', 'guard_name' => 'web']);

        $role = $this->service->create('with-perms', ['view roles']);

        $this->assertTrue($role->relationLoaded('permissions'));
    }

    public function test_update_renames_role(): void
    {
        $tenant = $this->bindFreshTenant();
        $role = Role::create(['name' => 'old-name', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $this->service->update($role, 'new-name');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'new-name']);
    }

    public function test_update_throws_when_renaming_system_role(): void
    {
        $tenant = $this->bindFreshTenant();
        $adminRole = Role::create(['name' => RoleTemplatesSeeder::ADMIN_ROLE, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->update($adminRole, 'renamed-admin');
    }

    public function test_update_throws_when_new_name_conflicts_with_existing_role_in_same_tenant(): void
    {
        $tenant = $this->bindFreshTenant();
        Role::create(['name' => 'existing', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $role = Role::create(['name' => 'my-role', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->update($role, 'existing');
    }

    public function test_update_same_name_does_not_throw(): void
    {
        $tenant = $this->bindFreshTenant();
        $role = Role::create(['name' => 'stable-role', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $result = $this->service->update($role, 'stable-role');

        $this->assertEquals('stable-role', $result->name);
    }

    public function test_update_syncs_permissions(): void
    {
        $tenant = $this->bindFreshTenant();
        Permission::firstOrCreate(['name' => 'edit employees', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'updatable', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $this->service->update($role, 'updatable', ['edit employees']);

        $this->assertTrue($role->fresh()->hasPermissionTo('edit employees'));
    }

    public function test_delete_removes_role_from_database(): void
    {
        $tenant = $this->bindFreshTenant();
        $role = Role::create(['name' => 'deletable', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $this->service->delete($role);

        $this->assertDatabaseMissing('roles', ['name' => 'deletable']);
    }

    public function test_delete_throws_for_system_role(): void
    {
        $tenant = $this->bindFreshTenant();
        $adminRole = Role::create(['name' => RoleTemplatesSeeder::ADMIN_ROLE, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->delete($adminRole);
    }

    public function test_delete_throws_when_role_has_users(): void
    {
        $tenant = $this->bindFreshTenant();
        $role = Role::create(['name' => 'in-use', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $user = User::factory()->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);
        $user->assignRole('in-use');

        $this->expectException(InvalidArgumentException::class);

        $this->service->delete($role);
    }

    public function test_get_permissions_grouped_returns_dto_shape(): void
    {
        $this->bindFreshTenant();
        $this->seed(PermissionSeeder::class);

        $grouped = $this->service->getPermissionsGrouped();

        $this->assertNotEmpty($grouped);
        $this->assertContainsOnlyInstancesOf(PermissionGroupData::class, $grouped);

        $employees = collect($grouped)->firstWhere('group', 'employees');
        $this->assertNotNull($employees);
        $this->assertSame(PermissionEnum::ViewEmployees->groupLabel(), $employees->group_label);
        $this->assertNotEmpty($employees->permissions);
        $this->assertSame(PermissionEnum::ViewEmployees, $employees->permissions[0]->name);
    }

    public function test_get_permissions_grouped_covers_every_enum_case(): void
    {
        $this->bindFreshTenant();
        $this->seed(PermissionSeeder::class);

        $grouped = $this->service->getPermissionsGrouped();

        $totalPermissions = collect($grouped)->sum(fn (PermissionGroupData $g) => count($g->permissions));

        $this->assertSame(count(PermissionEnum::cases()), $totalPermissions);
    }
}
