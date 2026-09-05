<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class UserApiControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    private const USER_STRUCTURE = ['id', 'name', 'email', 'is_active', 'locale', 'roles', 'created_at'];

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_users(): void
    {
        $user = $this->userWithPermission('view users');
        User::factory()->count(3)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users');

        $response->assertOk();
        $response->assertJsonStructure([
            'current_page',
            'data' => ['*' => self::USER_STRUCTURE],
            'total',
        ]);
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/users');

        $response->assertForbidden();
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertUnauthorized();
    }

    public function test_index_filters_users_by_search_query(): void
    {
        $user = $this->userWithPermission('view users');
        User::factory()->create(['name' => 'Searchable Person', 'email' => 'searchable@example.com']);
        User::factory()->create(['name' => 'Other Person', 'email' => 'other@example.com']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users?filter[search]=Searchable');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('data.0.name', 'Searchable Person');
    }

    public function test_index_respects_per_page_parameter(): void
    {
        $user = $this->userWithPermission('view users');
        User::factory()->count(5)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users?per_page=2');

        $response->assertOk();
        $response->assertJsonPath('per_page', 2);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_users_by_active_status(): void
    {
        $user = $this->userWithPermission('view users');
        User::factory()->create(['is_active' => true]);
        User::factory()->inactive()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users?filter[is_active]=true');

        $response->assertOk();
        foreach ($response->json('data') as $item) {
            $this->assertTrue($item['is_active']);
        }
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_user(): void
    {
        $actor = $this->userWithPermission('view users');
        $target = User::factory()->create(['name' => 'Target User']);
        Sanctum::actingAs($actor);

        $response = $this->getJson("/api/users/{$target->id}");

        $response->assertOk();
        $response->assertJsonStructure(self::USER_STRUCTURE);
        $response->assertJsonPath('name', 'Target User');
        $response->assertJsonPath('id', $target->id);
    }

    public function test_show_is_forbidden_without_permission(): void
    {
        $target = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/users/{$target->id}");

        $response->assertForbidden();
    }

    public function test_show_returns_404_for_nonexistent_user(): void
    {
        $actor = $this->userWithPermission('view users');
        Sanctum::actingAs($actor);

        $response = $this->getJson('/api/users/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    public function test_show_requires_authentication(): void
    {
        $target = User::factory()->create();

        $response = $this->getJson("/api/users/{$target->id}");

        $response->assertUnauthorized();
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_changes_user_data_in_database(): void
    {
        $actor = $this->userWithPermission('edit users');
        $target = User::factory()->create(['name' => 'Old Name']);
        $role = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
        Sanctum::actingAs($actor);

        $response = $this->putJson("/api/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => $target->email,
            'is_active' => true,
            'roles' => [$role->name],
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'Updated Name');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Updated Name']);
    }

    public function test_update_is_forbidden_without_permission(): void
    {
        $target = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->putJson("/api/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => $target->email,
            'is_active' => true,
            'roles' => [],
        ]);

        $response->assertForbidden();
    }

    public function test_update_with_missing_name_returns_422(): void
    {
        $actor = $this->userWithPermission('edit users');
        $target = User::factory()->create();
        Sanctum::actingAs($actor);

        $response = $this->putJson("/api/users/{$target->id}", [
            'name' => '',
            'email' => $target->email,
            'is_active' => true,
            'roles' => [],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_update_with_email_of_another_user_returns_422(): void
    {
        $actor = $this->userWithPermission('edit users');
        User::factory()->create(['email' => 'taken@example.com']);
        $target = User::factory()->create();
        Sanctum::actingAs($actor);

        $response = $this->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => 'taken@example.com',
            'is_active' => true,
            'roles' => [],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_update_requires_authentication(): void
    {
        $target = User::factory()->create();

        $response = $this->putJson("/api/users/{$target->id}", [
            'name' => 'Name',
            'email' => $target->email,
            'is_active' => true,
            'roles' => [],
        ]);

        $response->assertUnauthorized();
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_user(): void
    {
        $actor = $this->userWithPermission('delete users');
        $target = User::factory()->create();
        Sanctum::actingAs($actor);

        $response = $this->deleteJson("/api/users/{$target->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_destroy_is_forbidden_without_permission(): void
    {
        $target = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->deleteJson("/api/users/{$target->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_destroy_is_forbidden_when_deleting_self(): void
    {
        $actor = $this->userWithPermission('delete users');
        Sanctum::actingAs($actor);

        $response = $this->deleteJson("/api/users/{$actor->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $actor->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_user(): void
    {
        $actor = $this->userWithPermission('delete users');
        Sanctum::actingAs($actor);

        $response = $this->deleteJson('/api/users/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    public function test_destroy_requires_authentication(): void
    {
        $target = User::factory()->create();

        $response = $this->deleteJson("/api/users/{$target->id}");

        $response->assertUnauthorized();
    }
}
