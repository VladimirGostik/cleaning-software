<?php

declare(strict_types=1);

namespace Tests\Feature\Roles;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class RoleControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private string $testPermission = 'view employees';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => $this->testPermission, 'guard_name' => 'web']);
    }

    /** Creates a role scoped to the given tenant (bypassing whatever team is currently bound). */
    private function roleInTenant(string $tenantId, string $name): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'web', $this->teamKey() => $tenantId]);
    }

    private function teamKey(): string
    {
        return app(PermissionRegistrar::class)->teamsKey;
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
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)->get('/roles');

        $response->assertForbidden();
    }

    public function test_index_only_lists_roles_of_active_tenant(): void
    {
        $user = $this->userWithPermission('view roles');
        $foreignTenant = Tenant::factory()->create();
        $this->roleInTenant($foreignTenant->id, 'foreign-role');

        $response = $this->withoutVite()->actingAs($user)->get('/roles');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('roles.data', fn ($data) => collect($data)->pluck('name')->doesntContain('foreign-role')),
        );
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
        $user = $this->userWithPermission();

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
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)->post('/roles', ['name' => 'new-role', 'permissions' => [$this->testPermission]]);

        $response->assertForbidden();
    }

    public function test_store_with_duplicate_name_in_same_tenant_redirects_with_error(): void
    {
        $user = $this->userWithPermission('create roles');
        $this->roleInTenant((string) app('current_tenant_id'), 'existing-role');

        $response = $this->actingAs($user)->post('/roles', [
            'name' => 'existing-role',
            'permissions' => [$this->testPermission],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_store_with_same_name_in_another_tenant_succeeds(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $this->roleInTenant($foreignTenant->id, 'shared-name');

        $user = $this->userWithPermission('create roles');

        $response = $this->actingAs($user)->post('/roles', [
            'name' => 'shared-name',
            'permissions' => [$this->testPermission],
        ]);

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('success');
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
        $role = $this->roleInTenant((string) app('current_tenant_id'), 'editable-role');

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
        $user = $this->userWithPermission();
        $role = $this->roleInTenant((string) app('current_tenant_id'), 'some-role');

        $response = $this->actingAs($user)->get("/roles/{$role->id}/edit");

        $response->assertForbidden();
    }

    public function test_edit_of_role_from_another_tenant_is_forbidden(): void
    {
        $user = $this->userWithPermission('edit roles');
        $foreignTenant = Tenant::factory()->create();
        $role = $this->roleInTenant($foreignTenant->id, 'foreign-role');

        $response = $this->actingAs($user)->get("/roles/{$role->id}/edit");

        $response->assertForbidden();
    }

    public function test_update_changes_role_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('edit roles');
        $role = $this->roleInTenant((string) app('current_tenant_id'), 'old-role');

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
        $user = $this->userWithPermission();
        $role = $this->roleInTenant((string) app('current_tenant_id'), 'some-role');

        $response = $this->actingAs($user)->put("/roles/{$role->id}", [
            'name' => 'updated-role',
            'permissions' => [$this->testPermission],
        ]);

        $response->assertForbidden();
    }

    public function test_update_system_role_rename_redirects_with_error(): void
    {
        $user = $this->userWithPermission('edit roles');
        /** @var Role $adminRole */
        $adminRole = Role::inTenant((string) app('current_tenant_id'))->where('name', RoleTemplatesSeeder::ADMIN_ROLE)->first()
            ?? $this->roleInTenant((string) app('current_tenant_id'), RoleTemplatesSeeder::ADMIN_ROLE);

        $response = $this->actingAs($user)->put("/roles/{$adminRole->id}", [
            'name' => 'renamed-admin',
            'permissions' => [$this->testPermission],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $adminRole->id, 'name' => RoleTemplatesSeeder::ADMIN_ROLE]);
    }

    public function test_destroy_deletes_role_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('delete roles');
        $role = $this->roleInTenant((string) app('current_tenant_id'), 'deletable');

        $response = $this->actingAs($user)->delete("/roles/{$role->id}");

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['name' => 'deletable']);
    }

    public function test_destroy_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();
        $role = $this->roleInTenant((string) app('current_tenant_id'), 'some-role');

        $response = $this->actingAs($user)->delete("/roles/{$role->id}");

        $response->assertForbidden();
    }

    public function test_destroy_system_role_is_forbidden_by_policy(): void
    {
        $user = $this->userWithPermission('delete roles');
        $tenantId = (string) app('current_tenant_id');
        /** @var Role $adminRole */
        $adminRole = Role::inTenant($tenantId)->where('name', RoleTemplatesSeeder::ADMIN_ROLE)->first()
            ?? $this->roleInTenant($tenantId, RoleTemplatesSeeder::ADMIN_ROLE);

        $response = $this->actingAs($user)->delete("/roles/{$adminRole->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('roles', ['name' => RoleTemplatesSeeder::ADMIN_ROLE]);
    }

    public function test_destroy_role_with_assigned_users_redirects_with_error(): void
    {
        $actor = $this->userWithPermission('delete roles');
        $tenantId = (string) app('current_tenant_id');
        $role = $this->roleInTenant($tenantId, 'in-use-role');

        $userWithRole = User::factory()->create();
        TenantMembership::create(['user_id' => $userWithRole->id, 'tenant_id' => $tenantId, 'is_active' => true, 'joined_at' => now()]);
        $userWithRole->assignRole($role);

        $response = $this->actingAs($actor)->delete("/roles/{$role->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['name' => 'in-use-role']);
    }
}
