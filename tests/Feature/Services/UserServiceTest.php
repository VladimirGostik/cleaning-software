<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Data\CreateUserData;
use App\Data\UpdateUserData;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_create_persists_user_with_correct_attributes(): void
    {
        $data = new CreateUserData(
            name: 'John Doe',
            email: 'john@example.com',
            password: 'password123',
            password_confirmation: 'password123',
            is_active: true,
            roles: [],
        );

        $user = $this->service->create($data);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com', 'is_active' => true]);
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_create_assigns_roles_when_provided(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $data = new CreateUserData(
            name: 'Jane',
            email: 'jane@example.com',
            password: 'password123',
            password_confirmation: 'password123',
            is_active: true,
            roles: ['editor'],
        );

        $user = $this->service->create($data);

        $this->assertTrue($user->hasRole('editor'));
    }

    public function test_create_assigns_no_roles_when_empty(): void
    {
        $data = new CreateUserData(
            name: 'Jane',
            email: 'jane@example.com',
            password: 'password123',
            password_confirmation: 'password123',
            is_active: true,
            roles: [],
        );

        $user = $this->service->create($data);

        $this->assertCount(0, $user->roles);
    }

    public function test_create_returns_user_with_roles_loaded(): void
    {
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $data = new CreateUserData(
            name: 'Alice',
            email: 'alice@example.com',
            password: 'password123',
            password_confirmation: 'password123',
            is_active: true,
            roles: ['viewer'],
        );

        $result = $this->service->create($data);

        $this->assertTrue($result->relationLoaded('roles'));
        $this->assertCount(1, $result->roles);
    }

    public function test_update_changes_user_attributes(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com', 'is_active' => true]);
        $data = new UpdateUserData(name: 'New Name', email: 'new@example.com', is_active: false, roles: []);

        $this->service->update($user, $data);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'email' => 'new@example.com', 'is_active' => false]);
    }

    public function test_update_adds_new_roles(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'manager', 'guard_name' => 'web']);
        $data = new UpdateUserData(name: $user->name, email: $user->email, is_active: true, roles: ['manager']);

        $this->service->update($user, $data);

        $this->assertTrue($user->fresh()->hasRole('manager'));
    }

    public function test_update_removes_old_roles(): void
    {
        $role = Role::create(['name' => 'old-role', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('old-role');

        $data = new UpdateUserData(name: $user->name, email: $user->email, is_active: true, roles: []);

        $this->service->update($user, $data);

        $this->assertFalse($user->fresh()->hasRole('old-role'));
    }

    public function test_update_clears_all_roles_when_empty_array_given(): void
    {
        $role1 = Role::create(['name' => 'role-a', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role-b', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->syncRoles(['role-a', 'role-b']);

        $data = new UpdateUserData(name: $user->name, email: $user->email, is_active: true, roles: []);

        $this->service->update($user, $data);

        $this->assertCount(0, $user->fresh()->roles);
    }

    public function test_update_returns_user_with_roles_loaded(): void
    {
        $role = Role::create(['name' => 'tester', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $data = new UpdateUserData(name: $user->name, email: $user->email, is_active: true, roles: ['tester']);

        $result = $this->service->update($user, $data);

        $this->assertTrue($result->relationLoaded('roles'));
    }

    public function test_delete_removes_user_from_database(): void
    {
        $user = User::factory()->create();

        $this->service->delete($user);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delete_removes_role_assignments_before_deleting(): void
    {
        $role = Role::create(['name' => 'cleanup-role', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('cleanup-role');

        $this->service->delete($user);

        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $user->id]);
    }
}
