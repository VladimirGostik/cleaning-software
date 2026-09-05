<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class UserControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private Role $testRole;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->testRole = Role::firstOrCreate(['name' => 'test-member', 'guard_name' => 'web']);
    }

    public function test_index_is_accessible_with_view_users_permission(): void
    {
        $user = $this->userWithPermission('view users');

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
        $user = User::factory()->create();

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
        $user = $this->userWithPermission('view users');
        User::factory()->create(['name' => 'Searchable Person', 'email' => 'searchable@example.com']);
        User::factory()->create(['name' => 'Other Person', 'email' => 'other@example.com']);

        $response = $this->withoutVite()->actingAs($user)->get('/users?filter[search]=Searchable');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->where('users.data.0.name', 'Searchable Person'),
        );
    }

    public function test_create_is_accessible_with_create_users_permission(): void
    {
        $user = $this->userWithPermission('create users');

        $response = $this->withoutVite()->actingAs($user)->get('/users/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Form')
            ->has('roles'),
        );
    }

    public function test_create_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/users/create');

        $response->assertForbidden();
    }

    public function test_store_creates_user_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('create users');

        $response = $this->actingAs($user)->post('/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true,
            'roles' => [$this->testRole->name],
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_store_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => [$this->testRole->name],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }

    public function test_store_with_missing_name_returns_validation_error(): void
    {
        $user = $this->userWithPermission('create users');

        $response = $this->actingAs($user)->post('/users', [
            'name' => '',
            'email' => 'valid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => [$this->testRole->name],
        ]);

        $response->assertInvalid(['name']);
    }

    public function test_store_with_duplicate_email_returns_validation_error(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $user = $this->userWithPermission('create users');

        $response = $this->actingAs($user)->post('/users', [
            'name' => 'New User',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => [$this->testRole->name],
        ]);

        $response->assertInvalid(['email']);
    }

    public function test_store_with_mismatched_passwords_returns_validation_error(): void
    {
        $user = $this->userWithPermission('create users');

        $response = $this->actingAs($user)->post('/users', [
            'name' => 'New User',
            'email' => 'valid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
            'roles' => [$this->testRole->name],
        ]);

        $response->assertInvalid(['password']);
    }

    public function test_edit_is_accessible_with_edit_users_permission(): void
    {
        $user = $this->userWithPermission('edit users');
        $target = User::factory()->create();

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
        $user = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($user)->get("/users/{$target->id}/edit");

        $response->assertForbidden();
    }

    public function test_update_changes_user_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('edit users');
        $target = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->put("/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => $target->email,
            'is_active' => true,
            'roles' => [$this->testRole->name],
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Updated Name']);
    }

    public function test_update_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($user)->put("/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => $target->email,
            'is_active' => true,
            'roles' => [$this->testRole->name],
        ]);

        $response->assertForbidden();
    }

    public function test_update_with_email_of_another_user_returns_validation_error(): void
    {
        $user = $this->userWithPermission('edit users');
        $other = User::factory()->create(['email' => 'other@example.com']);
        $target = User::factory()->create();

        $response = $this->actingAs($user)->put("/users/{$target->id}", [
            'name' => $target->name,
            'email' => 'other@example.com',
            'is_active' => true,
            'roles' => [$this->testRole->name],
        ]);

        $response->assertInvalid(['email']);
    }

    public function test_destroy_deletes_user_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('delete users');
        $target = User::factory()->create();

        $response = $this->actingAs($user)->delete("/users/{$target->id}");

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_destroy_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($user)->delete("/users/{$target->id}");

        $response->assertForbidden();
    }

    public function test_destroy_is_forbidden_when_deleting_self(): void
    {
        $user = $this->userWithPermission('delete users');

        $response = $this->actingAs($user)->delete("/users/{$user->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
