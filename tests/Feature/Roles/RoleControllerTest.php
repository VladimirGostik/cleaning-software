<?php

declare(strict_types=1);

namespace Tests\Feature\Roles;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class RoleControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private string $testPermission = 'view users';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => $this->testPermission, 'guard_name' => 'web']);
    }

    public function test_index_is_accessible_with_view_roles_permission(): void
    {
        $user = $this->userWithPermission('view roles');

        $response = $this->withoutVite()->actingAs($user)->get('/roles');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Roles/Index')
            ->has('roles')
            ->has('filters'),
        );
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/roles');

        $response->assertForbidden();
    }

    public function test_create_is_accessible_with_create_roles_permission(): void
    {
        $user = $this->userWithPermission('create roles');

        $response = $this->withoutVite()->actingAs($user)->get('/roles/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Roles/Form')
            ->has('permissions'),
        );
    }

    public function test_create_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/roles/create');

        $response->assertForbidden();
    }

    public function test_store_creates_role_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('create roles');

        $response = $this->actingAs($user)->post('/roles', [
            'name' => 'new-role',
            'permissions' => [$this->testPermission],
        ]);

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('roles', ['name' => 'new-role']);
    }

    public function test_store_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/roles', ['name' => 'new-role', 'permissions' => [$this->testPermission]]);

        $response->assertForbidden();
    }

    public function test_store_with_duplicate_name_redirects_with_error(): void
    {
        Role::create(['name' => 'existing-role', 'guard_name' => 'web']);
        $user = $this->userWithPermission('create roles');

        $response = $this->actingAs($user)->post('/roles', [
            'name' => 'existing-role',
            'permissions' => [$this->testPermission],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_store_with_missing_name_returns_validation_error(): void
    {
        $user = $this->userWithPermission('create roles');

        $response = $this->actingAs($user)->post('/roles', [
            'name' => '',
            'permissions' => [$this->testPermission],
        ]);

        $response->assertInvalid(['name']);
    }

    public function test_edit_is_accessible_with_edit_roles_permission(): void
    {
        $user = $this->userWithPermission('edit roles');
        $role = Role::create(['name' => 'editable-role', 'guard_name' => 'web']);

        $response = $this->withoutVite()->actingAs($user)->get("/roles/{$role->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Roles/Form')
            ->has('role')
            ->has('permissions'),
        );
    }

    public function test_edit_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'some-role', 'guard_name' => 'web']);

        $response = $this->actingAs($user)->get("/roles/{$role->id}/edit");

        $response->assertForbidden();
    }

    public function test_update_changes_role_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('edit roles');
        $role = Role::create(['name' => 'old-role', 'guard_name' => 'web']);

        $response = $this->actingAs($user)->put("/roles/{$role->id}", [
            'name' => 'updated-role',
            'permissions' => [$this->testPermission],
        ]);

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'updated-role']);
    }

    public function test_update_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'some-role', 'guard_name' => 'web']);

        $response = $this->actingAs($user)->put("/roles/{$role->id}", [
            'name' => 'updated-role',
            'permissions' => [$this->testPermission],
        ]);

        $response->assertForbidden();
    }

    public function test_update_system_role_rename_redirects_with_error(): void
    {
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user = $this->userWithPermission('edit roles');

        $response = $this->actingAs($user)->put("/roles/{$adminRole->id}", [
            'name' => 'renamed-admin',
            'permissions' => [$this->testPermission],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $adminRole->id, 'name' => 'admin']);
    }

    public function test_destroy_deletes_role_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('delete roles');
        $role = Role::create(['name' => 'deletable', 'guard_name' => 'web']);

        $response = $this->actingAs($user)->delete("/roles/{$role->id}");

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['name' => 'deletable']);
    }

    public function test_destroy_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'some-role', 'guard_name' => 'web']);

        $response = $this->actingAs($user)->delete("/roles/{$role->id}");

        $response->assertForbidden();
    }

    public function test_destroy_system_role_is_forbidden_by_policy(): void
    {
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user = $this->userWithPermission('delete roles');

        $response = $this->actingAs($user)->delete("/roles/{$adminRole->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('roles', ['name' => 'admin']);
    }

    public function test_destroy_role_with_assigned_users_redirects_with_error(): void
    {
        $role = Role::create(['name' => 'in-use-role', 'guard_name' => 'web']);
        $userWithRole = User::factory()->create();
        $userWithRole->assignRole('in-use-role');

        $actor = $this->userWithPermission('delete roles');

        $response = $this->actingAs($actor)->delete("/roles/{$role->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['name' => 'in-use-role']);
    }
}
