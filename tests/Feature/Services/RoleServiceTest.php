<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
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

    public function test_create_persists_role_with_correct_guard(): void
    {
        $role = $this->service->create('editor');

        $this->assertDatabaseHas('roles', ['name' => 'editor', 'guard_name' => 'web']);
        $this->assertInstanceOf(Role::class, $role);
    }

    public function test_create_syncs_permissions(): void
    {
        Permission::firstOrCreate(['name' => 'view users', 'guard_name' => 'web']);

        $role = $this->service->create('limited', ['view users']);

        $this->assertTrue($role->hasPermissionTo('view users'));
    }

    public function test_create_throws_when_role_name_already_exists(): void
    {
        Role::create(['name' => 'duplicate', 'guard_name' => 'web']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/duplicate/');

        $this->service->create('duplicate');
    }

    public function test_create_returns_role_with_permissions_loaded(): void
    {
        Permission::firstOrCreate(['name' => 'view roles', 'guard_name' => 'web']);

        $role = $this->service->create('with-perms', ['view roles']);

        $this->assertTrue($role->relationLoaded('permissions'));
    }

    public function test_update_renames_role(): void
    {
        $role = Role::create(['name' => 'old-name', 'guard_name' => 'web']);

        $this->service->update($role, 'new-name');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'new-name']);
    }

    public function test_update_throws_when_renaming_system_role(): void
    {
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->expectException(InvalidArgumentException::class);

        $this->service->update($adminRole, 'renamed-admin');
    }

    public function test_update_throws_when_new_name_conflicts_with_existing_role(): void
    {
        Role::create(['name' => 'existing', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'my-role', 'guard_name' => 'web']);

        $this->expectException(InvalidArgumentException::class);

        $this->service->update($role, 'existing');
    }

    public function test_update_same_name_does_not_throw(): void
    {
        $role = Role::create(['name' => 'stable-role', 'guard_name' => 'web']);

        $result = $this->service->update($role, 'stable-role');

        $this->assertEquals('stable-role', $result->name);
    }

    public function test_update_syncs_permissions(): void
    {
        Permission::firstOrCreate(['name' => 'edit users', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'updatable', 'guard_name' => 'web']);

        $this->service->update($role, 'updatable', ['edit users']);

        $this->assertTrue($role->fresh()->hasPermissionTo('edit users'));
    }

    public function test_delete_removes_role_from_database(): void
    {
        $role = Role::create(['name' => 'deletable', 'guard_name' => 'web']);

        $this->service->delete($role);

        $this->assertDatabaseMissing('roles', ['name' => 'deletable']);
    }

    public function test_delete_throws_for_system_role(): void
    {
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->expectException(InvalidArgumentException::class);

        $this->service->delete($adminRole);
    }

    public function test_delete_throws_when_role_has_users(): void
    {
        $role = Role::create(['name' => 'in-use', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('in-use');

        $this->expectException(InvalidArgumentException::class);

        $this->service->delete($role);
    }

    public function test_get_permissions_grouped_returns_correct_structure(): void
    {
        Permission::firstOrCreate(['name' => 'view users', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create users', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view roles', 'guard_name' => 'web']);

        $grouped = $this->service->getPermissionsGrouped();

        $this->assertIsArray($grouped);
        $this->assertNotEmpty($grouped);

        foreach ($grouped as $group) {
            $this->assertArrayHasKey('group', $group);
            $this->assertArrayHasKey('permissions', $group);
            $this->assertIsArray($group['permissions']);
        }
    }

    public function test_get_permissions_grouped_uses_other_for_single_word_permission(): void
    {
        Permission::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $grouped = $this->service->getPermissionsGrouped();

        $groups = array_column($grouped, 'group');
        $this->assertContains('other', $groups);
    }
}
