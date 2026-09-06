<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class UserControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    /** Creates a role in the given tenant so `roles.*` DTO validation accepts it. */
    private function roleInTenant(string $tenantId, string $name = 'test-member'): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'web', 'tenant_id' => $tenantId]);
    }

    /** Adds `$target` as an active member of `$tenant` so tenant-scoped policies allow access. */
    private function addMember(Tenant $tenant, User $target): void
    {
        TenantMembership::create([
            'user_id' => $target->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    public function test_index_is_accessible_with_view_users_permission(): void
    {
        $user = $this->userWithPermission('view employees');

        $response = $this->withoutVite()->actingAs($user)->get('/users');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->has('users')
            ->has('filters')
            ->has('filterOptions'),
        );
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)->get('/users');

        $response->assertForbidden();
    }

    public function test_index_redirects_guest_to_login(): void
    {
        $response = $this->get('/users');

        $response->assertRedirect(route('login'));
    }

    public function test_index_filters_users_by_search_query(): void
    {
        $user = $this->userWithPermission('view employees');
        $tenantId = (string) app('current_tenant_id');
        $tenant = Tenant::find($tenantId);

        $searchable = User::factory()->create(['name' => 'Searchable Person', 'email' => 'searchable@example.com']);
        $other = User::factory()->create(['name' => 'Other Person', 'email' => 'other@example.com']);
        $this->addMember($tenant, $searchable);
        $this->addMember($tenant, $other);

        $response = $this->withoutVite()->actingAs($user)->get('/users?filter[search]=Searchable');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->where('users.data.0.name', 'Searchable Person'),
        );
    }

    public function test_index_only_lists_members_of_active_tenant(): void
    {
        $user = $this->userWithPermission('view employees');
        $outsider = User::factory()->create(['name' => 'Outsider']);

        $response = $this->withoutVite()->actingAs($user)->get('/users');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->where('users.data', fn ($data) => collect($data)->pluck('id')->doesntContain($outsider->id)),
        );
    }

    public function test_create_is_accessible_with_create_users_permission(): void
    {
        $user = $this->userWithPermission('create employees');

        $response = $this->withoutVite()->actingAs($user)->get('/users/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Form')
            ->has('roles'),
        );
    }

    public function test_create_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)->get('/users/create');

        $response->assertForbidden();
    }

    public function test_store_creates_user_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('create employees');
        $role = $this->roleInTenant((string) app('current_tenant_id'));

        $response = $this->actingAs($user)->post('/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true,
            'roles' => [$role->name],
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_store_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();
        $role = $this->roleInTenant((string) app('current_tenant_id'));

        $response = $this->actingAs($user)->post('/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => [$role->name],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }

    public function test_store_with_missing_name_returns_validation_error(): void
    {
        $user = $this->userWithPermission('create employees');
        $role = $this->roleInTenant((string) app('current_tenant_id'));

        $response = $this->actingAs($user)->post('/users', [
            'name' => '',
            'email' => 'valid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => [$role->name],
        ]);

        $response->assertInvalid(['name']);
    }

    public function test_store_with_existing_email_links_account_without_password(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $user = $this->userWithPermission('create employees');
        $role = $this->roleInTenant((string) app('current_tenant_id'));

        $response = $this->actingAs($user)->post('/users', [
            'name' => $existing->name,
            'email' => 'taken@example.com',
            'roles' => [$role->name],
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('tenant_memberships', [
            'user_id' => $existing->id,
            'tenant_id' => app('current_tenant_id'),
        ]);
    }

    public function test_store_with_already_active_member_returns_validation_error(): void
    {
        $user = $this->userWithPermission('create employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $existing = User::factory()->create(['email' => 'member@example.com']);
        $this->addMember($tenant, $existing);
        $role = $this->roleInTenant($tenant->id);

        $response = $this->actingAs($user)->post('/users', [
            'name' => $existing->name,
            'email' => 'member@example.com',
            'roles' => [$role->name],
        ]);

        $response->assertInvalid(['email']);
    }

    public function test_store_with_mismatched_passwords_returns_validation_error(): void
    {
        $user = $this->userWithPermission('create employees');
        $role = $this->roleInTenant((string) app('current_tenant_id'));

        $response = $this->actingAs($user)->post('/users', [
            'name' => 'New User',
            'email' => 'valid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
            'roles' => [$role->name],
        ]);

        $response->assertInvalid(['password']);
    }

    public function test_edit_is_accessible_with_edit_users_permission(): void
    {
        $user = $this->userWithPermission('edit employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $target = User::factory()->create();
        $this->addMember($tenant, $target);

        $response = $this->withoutVite()->actingAs($user)->get("/users/{$target->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Form')
            ->has('user')
            ->has('roles'),
        );
    }

    public function test_edit_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $target = User::factory()->create();
        $this->addMember($tenant, $target);

        $response = $this->actingAs($user)->get("/users/{$target->id}/edit");

        $response->assertForbidden();
    }

    public function test_edit_of_user_outside_tenant_is_forbidden(): void
    {
        $user = $this->userWithPermission('edit employees');
        $target = User::factory()->create();

        $response = $this->actingAs($user)->get("/users/{$target->id}/edit");

        $response->assertForbidden();
    }

    public function test_update_changes_user_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('edit employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $target = User::factory()->create(['name' => 'Old Name']);
        $this->addMember($tenant, $target);
        $role = $this->roleInTenant($tenant->id);

        $response = $this->actingAs($user)->put("/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => $target->email,
            'is_active' => true,
            'roles' => [$role->name],
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Updated Name']);
    }

    public function test_update_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $target = User::factory()->create();
        $this->addMember($tenant, $target);
        $role = $this->roleInTenant($tenant->id);

        $response = $this->actingAs($user)->put("/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => $target->email,
            'is_active' => true,
            'roles' => [$role->name],
        ]);

        $response->assertForbidden();
    }

    public function test_update_with_email_of_another_user_returns_validation_error(): void
    {
        $user = $this->userWithPermission('edit employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $other = User::factory()->create(['email' => 'other@example.com']);
        $target = User::factory()->create();
        $this->addMember($tenant, $target);
        $role = $this->roleInTenant($tenant->id);

        $response = $this->actingAs($user)->put("/users/{$target->id}", [
            'name' => $target->name,
            'email' => 'other@example.com',
            'is_active' => true,
            'roles' => [$role->name],
        ]);

        $response->assertInvalid(['email']);
    }

    public function test_destroy_removes_membership_but_keeps_user_row(): void
    {
        $user = $this->userWithPermission('delete employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $target = User::factory()->create();
        $this->addMember($tenant, $target);

        $response = $this->actingAs($user)->delete("/users/{$target->id}");

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('tenant_memberships', ['user_id' => $target->id, 'tenant_id' => $tenant->id]);
    }

    public function test_destroy_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $target = User::factory()->create();
        $this->addMember($tenant, $target);

        $response = $this->actingAs($user)->delete("/users/{$target->id}");

        $response->assertForbidden();
    }

    public function test_destroy_is_forbidden_when_deleting_self(): void
    {
        $user = $this->userWithPermission('delete employees');

        $response = $this->actingAs($user)->delete("/users/{$user->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_destroy_is_forbidden_when_deleting_tenant_owner(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->addMember($tenant, $owner);

        $user = User::factory()->create();
        $this->addMember($tenant, $user);
        $role = $this->roleInTenant($tenant->id);
        $this->bindTenant($tenant);
        Permission::firstOrCreate(['name' => 'delete employees', 'guard_name' => 'web']);
        $role->syncPermissions(['delete employees']);
        $user->assignRole($role);

        $response = $this->actingAs($user)->delete("/users/{$owner->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }
}
