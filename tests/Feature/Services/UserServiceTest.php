<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Data\CreateUserData;
use App\Data\UpdateUserData;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserService::class);
    }

    /** Creates a tenant-scoped role holding `$permissions`, creating the Permission rows as needed. */
    private function roleWithPermissions(string $tenantId, string $name, array $permissions = []): Role
    {
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::create(['name' => $name, 'guard_name' => 'web', 'tenant_id' => $tenantId]);
        $role->syncPermissions($permissions);

        return $role;
    }

    /** Actor holding all-tenant-permissions (mirrors Admin) — never trips the subset guard. */
    private function omnipotentActor(string $tenantId): User
    {
        $actor = User::factory()->create();
        TenantMembership::create(['user_id' => $actor->id, 'tenant_id' => $tenantId, 'is_active' => true, 'joined_at' => now()]);
        $role = $this->roleWithPermissions($tenantId, 'omni', Permission::query()->pluck('name')->all());
        $actor->assignRole($role);

        return $actor;
    }

    public function test_create_persists_new_user_and_active_membership(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $actor = $this->omnipotentActor($tenant->id);

        $data = new CreateUserData(name: 'John Doe', email: 'john@example.com', password: 'password123', is_active: true, roles: []);

        $user = $this->service->create($data, $actor);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);
    }

    public function test_create_links_existing_user_ignoring_password(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $actor = $this->omnipotentActor($tenant->id);
        $existing = User::factory()->create(['email' => 'existing@example.com']);

        $data = new CreateUserData(name: $existing->name, email: 'existing@example.com', password: null, is_active: true, roles: []);

        $user = $this->service->create($data, $actor);

        $this->assertSame($existing->id, $user->id);
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $existing->id, 'tenant_id' => $tenant->id]);
    }

    public function test_create_assigns_tenant_scoped_roles(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $actor = $this->omnipotentActor($tenant->id);
        $role = $this->roleWithPermissions($tenant->id, 'editor');

        $data = new CreateUserData(name: 'Jane', email: 'jane@example.com', password: 'password123', is_active: true, roles: ['editor']);

        $user = $this->service->create($data, $actor);

        $this->assertTrue($user->hasRole($role));
    }

    public function test_create_fails_when_email_already_an_active_member(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $actor = $this->omnipotentActor($tenant->id);
        $existing = User::factory()->create(['email' => 'member@example.com']);
        TenantMembership::create(['user_id' => $existing->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        $data = new CreateUserData(name: $existing->name, email: 'member@example.com', password: null, is_active: true, roles: []);

        $this->expectException(ValidationException::class);
        $this->service->create($data, $actor);
    }

    public function test_create_fails_when_role_exceeds_actor_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $actor = User::factory()->create();
        TenantMembership::create(['user_id' => $actor->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);
        $limitedRole = $this->roleWithPermissions($tenant->id, 'limited-actor', ['view employees']);
        $actor->assignRole($limitedRole);

        $powerfulRole = $this->roleWithPermissions($tenant->id, 'powerful', ['view employees', 'delete employees']);

        $data = new CreateUserData(name: 'Escalator', email: 'escalate@example.com', password: 'password123', is_active: true, roles: ['powerful']);

        $this->expectException(ValidationException::class);
        $this->service->create($data, $actor);
        $this->assertDatabaseMissing('users', ['email' => 'escalate@example.com']);
    }

    public function test_update_changes_name_email_and_membership_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $actor = $this->omnipotentActor($tenant->id);
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        $data = new UpdateUserData(name: 'New Name', email: 'new@example.com', is_active: false, roles: []);
        $this->service->update($user, $data, $actor);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'email' => 'new@example.com']);
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => false]);
    }

    public function test_update_syncs_roles(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $actor = $this->omnipotentActor($tenant->id);
        $user = User::factory()->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);
        $role = $this->roleWithPermissions($tenant->id, 'manager');

        $data = new UpdateUserData(name: $user->name, email: $user->email, is_active: true, roles: ['manager']);
        $this->service->update($user, $data, $actor);

        $this->assertTrue($user->fresh()->hasRole($role));
    }

    public function test_update_clears_roles_when_empty_array_given(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $actor = $this->omnipotentActor($tenant->id);
        $user = User::factory()->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);
        $role = $this->roleWithPermissions($tenant->id, 'role-a');
        $user->assignRole($role);

        $data = new UpdateUserData(name: $user->name, email: $user->email, is_active: true, roles: []);
        $this->service->update($user, $data, $actor);

        $this->assertCount(0, $user->fresh()->roles);
    }

    public function test_delete_removes_membership_and_roles_but_keeps_user_row(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $user = User::factory()->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);
        $role = $this->roleWithPermissions($tenant->id, 'cleanup-role');
        $user->assignRole($role);

        $this->service->delete($user);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('tenant_memberships', ['user_id' => $user->id, 'tenant_id' => $tenant->id]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertFalse($user->fresh()->hasRole($role));
    }
}
