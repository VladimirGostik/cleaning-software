<?php

declare(strict_types=1);

namespace Tests\Feature\Objects;

use App\Enums\ObjectTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkBreakdown;
use App\Models\WorkBreakdownTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ObjectControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a tenant whose owner is on Pro plan (has Objects feature) and
     * authenticate the given role inside that tenant.
     */
    private function actingAsTenantUserWithObjectsFeature(string $roleName): Tenant
    {
        $owner = User::factory()->pro()->create(['is_active' => true, 'locale' => 'sk']);
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser($roleName, $tenant);

        return $tenant;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePayload(string $clientId, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $clientId,
            'type' => ObjectTypeEnum::Office->value,
            'name' => 'Test Office',
            'street' => 'Hlavná 1',
            'city' => 'Bratislava',
            'postal_code' => '811 01',
            'country' => 'SK',
            'access_code' => null,
            'key_box_code' => null,
            'key_count' => null,
            'special_instructions' => null,
            'area_sqm' => 120.50,
            'floor' => 2,
            'is_active' => true,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // INDEX — happy
    // -------------------------------------------------------------------------

    public function test_index_returns_own_tenant_objects_only(): void
    {
        // Arrange
        $owner = User::factory()->pro()->create(['is_active' => true, 'locale' => 'sk']);
        $tenantA = Tenant::factory()->forOwner($owner)->create();
        $tenantB = Tenant::factory()->create(); // different owner

        $this->actingAsTenantUser('Vlastník', $tenantA);

        $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenantA->id, 'client_id' => $clientA->id]);
        CleaningObject::factory()->count(2)->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

        // Act
        $response = $this->get(route('objects.index'));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Objects/Index')
                ->has('objects.data', 3),
        );
    }

    public function test_index_requires_view_objects_permission_and_passes(): void
    {
        // Arrange — Upratovačka has ViewObjects
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Upratovačka');

        // Act & Assert
        $this->get(route('objects.index'))->assertOk();
    }

    public function test_index_redirects_unauthenticated(): void
    {
        $this->get(route('objects.index'))->assertRedirect(route('login'));
    }

    public function test_index_filter_by_type(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        CleaningObject::factory()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'type' => ObjectTypeEnum::Office]);
        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'type' => ObjectTypeEnum::Apartment]);

        // Act
        $response = $this->get(route('objects.index', ['filter[type]' => 'office']));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->has('objects.data', 2),
        );
    }

    public function test_index_filter_by_client_id(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);

        CleaningObject::factory()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $clientA->id]);
        CleaningObject::factory()->count(4)->create(['tenant_id' => $tenant->id, 'client_id' => $clientB->id]);

        // Act
        $response = $this->get(route('objects.index', ['filter[client_id]' => $clientA->id]));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->has('objects.data', 2),
        );
    }

    public function test_index_filter_by_is_active(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);
        CleaningObject::factory()->inactive()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Act
        $response = $this->get(route('objects.index', ['filter[is_active]' => '0']));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->has('objects.data', 2),
        );
    }

    public function test_index_filter_by_search(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'name' => 'Alpha Office']);
        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'name' => 'Beta Apartment']);

        // Act
        $response = $this->get(route('objects.index', ['filter[search]' => 'Alpha']));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->has('objects.data', 1),
        );
    }

    public function test_index_empty_result_set(): void
    {
        // Arrange
        $this->actingAsTenantUserWithObjectsFeature('Vlastník');

        // Act
        $response = $this->get(route('objects.index'));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->has('objects.data', 0),
        );
    }

    public function test_index_per_page_boundary_10(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(15)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Act
        $response = $this->get(route('objects.index', ['per_page' => 10]));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->has('objects.data', 10),
        );
    }

    // -------------------------------------------------------------------------
    // INDEX — failure
    // -------------------------------------------------------------------------

    public function test_index_forbidden_without_view_objects_permission(): void
    {
        // Arrange — user with Pro-plan tenant but NO role assigned has no view objects permission
        $owner = User::factory()->pro()->create(['is_active' => true, 'locale' => 'sk']);
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Vlastník', $tenant);

        // Act — assign acting user to tenant context but strip all roles
        $user = auth()->user();
        /** @var User $user */
        $user->syncRoles([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Assert
        $this->get(route('objects.index'))->assertForbidden();
    }

    public function test_index_feature_gate_blocks_free_plan(): void
    {
        // Arrange — Free plan has NO Objects feature
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']); // Free plan
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Vlastník', $tenant);

        // Act & Assert
        $this->get(route('objects.index'))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // SHOW — happy
    // -------------------------------------------------------------------------

    public function test_show_returns_object_detail_with_empty_work_breakdowns(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Act
        $response = $this->get(route('objects.show', $object));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Objects/Show')
            ->where('object.id', $object->id)
            ->has('workBreakdowns', 0),
        );
    }

    public function test_show_work_breakdowns_includes_linked_breakdown_with_tasks(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $breakdown = WorkBreakdown::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
        ]);
        WorkBreakdownTask::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        // Act
        $response = $this->get(route('objects.show', $object));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('workBreakdowns', 1)
            ->where('workBreakdowns.0.id', $breakdown->id)
            ->has('workBreakdowns.0.tasks', 2),
        );
    }

    public function test_show_work_breakdowns_excludes_other_tenant_breakdowns(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Breakdown belonging to another tenant but referencing our object's ID
        $otherTenant = Tenant::factory()->create();
        WorkBreakdown::factory()->create([
            'tenant_id' => $otherTenant->id,
            'cleaning_object_id' => $object->id,
        ]);

        // Act
        $response = $this->get(route('objects.show', $object));

        // Assert — global TenantScope hides the foreign breakdown
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('workBreakdowns', 0),
        );
    }

    // -------------------------------------------------------------------------
    // SHOW — failure
    // -------------------------------------------------------------------------

    public function test_show_cross_tenant_object_returns_404(): void
    {
        // Arrange
        $owner = User::factory()->pro()->create(['is_active' => true, 'locale' => 'sk']);
        $tenantA = Tenant::factory()->forOwner($owner)->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenantA);

        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

        // Act — TenantScope hides objectB
        $this->get(route('objects.show', $objectB->id))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // STORE — happy
    // -------------------------------------------------------------------------

    public function test_store_creates_object_with_tenant_id(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->post(route('objects.store'), $this->storePayload($client->id));

        // Assert
        $response->assertRedirect(route('objects.index'));
        $this->assertDatabaseHas('objects', [
            'name' => 'Test Office',
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_store_activity_logged(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $this->post(route('objects.store'), $this->storePayload($client->id));

        // Assert
        $object = CleaningObject::where('name', 'Test Office')->firstOrFail();
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $object->id,
            'subject_type' => (new CleaningObject)->getMorphClass(),
        ]);
    }

    // -------------------------------------------------------------------------
    // STORE — failure
    // -------------------------------------------------------------------------

    public function test_store_forbidden_without_create_objects(): void
    {
        // Arrange — Vedúca has ViewObjects only, no CreateObjects
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vedúca');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act & Assert
        $this->post(route('objects.store'), $this->storePayload($client->id))->assertForbidden();
    }

    public function test_store_validation_missing_name(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->post(route('objects.store'), $this->storePayload($client->id, ['name' => '']));

        // Assert
        $response->assertSessionHasErrors('name');
    }

    public function test_store_validation_missing_type(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->post(route('objects.store'), $this->storePayload($client->id, ['type' => '']));

        // Assert
        $response->assertSessionHasErrors('type');
    }

    public function test_store_validation_nonexistent_client_id(): void
    {
        // Arrange
        $this->actingAsTenantUserWithObjectsFeature('Vlastník');

        // Act
        $response = $this->post(route('objects.store'), $this->storePayload('00000000-0000-0000-0000-000000000000'));

        // Assert
        $response->assertSessionHasErrors('client_id');
    }

    public function test_store_validation_negative_area_sqm(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->post(route('objects.store'), $this->storePayload($client->id, ['area_sqm' => -10]));

        // Assert
        $response->assertSessionHasErrors('area_sqm');
    }

    public function test_store_validation_negative_key_count(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->post(route('objects.store'), $this->storePayload($client->id, ['key_count' => -1]));

        // Assert
        $response->assertSessionHasErrors('key_count');
    }

    // -------------------------------------------------------------------------
    // STORE — edge
    // -------------------------------------------------------------------------

    public function test_store_nullable_fields_persist_null(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $this->post(route('objects.store'), $this->storePayload($client->id, [
            'name' => 'Nullable Test',
            'street' => null,
            'city' => null,
            'access_code' => null,
            'area_sqm' => null,
        ]));

        // Assert
        $this->assertDatabaseHas('objects', [
            'name' => 'Nullable Test',
            'street' => null,
            'city' => null,
            'access_code' => null,
        ]);
    }

    public function test_store_name_max_255_accepted(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act — exactly 255 chars
        $this->post(route('objects.store'), $this->storePayload($client->id, ['name' => str_repeat('a', 255)]))
            ->assertRedirect(route('objects.index'));
    }

    public function test_store_name_over_255_rejected(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act — 256 chars
        $this->post(route('objects.store'), $this->storePayload($client->id, ['name' => str_repeat('b', 256)]))
            ->assertSessionHasErrors('name');
    }

    public function test_store_cross_tenant_client_id_rejected(): void
    {
        // Arrange — client from another tenant
        $owner = User::factory()->pro()->create(['is_active' => true, 'locale' => 'sk']);
        $tenantA = Tenant::factory()->forOwner($owner)->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenantA);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

        // Act — global scope hides tenantB client from Exists validation
        $response = $this->post(route('objects.store'), $this->storePayload($clientB->id));

        // Assert
        $response->assertSessionHasErrors('client_id');
    }

    // -------------------------------------------------------------------------
    // UPDATE — happy
    // -------------------------------------------------------------------------

    public function test_update_modifies_object(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Act
        $response = $this->put(route('objects.update', $object), $this->storePayload($client->id, ['name' => 'Updated Name']));

        // Assert
        $response->assertRedirect(route('objects.show', $object));
        $this->assertDatabaseHas('objects', ['id' => $object->id, 'name' => 'Updated Name']);
    }

    public function test_update_allows_client_id_reassignment(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $clientA->id]);

        // Act
        $this->put(route('objects.update', $object), $this->storePayload($clientB->id));

        // Assert
        $this->assertDatabaseHas('objects', ['id' => $object->id, 'client_id' => $clientB->id]);
    }

    // -------------------------------------------------------------------------
    // UPDATE — failure
    // -------------------------------------------------------------------------

    public function test_update_forbidden_without_edit_objects(): void
    {
        // Arrange — Vedúca has only ViewObjects
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vedúca');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Act & Assert
        $this->put(route('objects.update', $object), $this->storePayload($client->id))->assertForbidden();
    }

    public function test_update_cross_tenant_object_returns_404(): void
    {
        // Arrange
        $owner = User::factory()->pro()->create(['is_active' => true, 'locale' => 'sk']);
        $tenantA = Tenant::factory()->forOwner($owner)->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenantA);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

        // Act — TenantScope hides objectB
        $this->put(route('objects.update', $objectB->id), $this->storePayload($clientB->id))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // UPDATE — edge
    // -------------------------------------------------------------------------

    public function test_update_toggle_is_active_to_false(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);

        // Act
        $this->put(route('objects.update', $object), $this->storePayload($client->id, ['is_active' => false]));

        // Assert
        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => false]);
    }

    public function test_update_toggle_is_active_back_to_true(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => false]);

        // Act
        $this->put(route('objects.update', $object), $this->storePayload($client->id, ['is_active' => true]));

        // Assert
        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => true]);
    }

    // -------------------------------------------------------------------------
    // DESTROY — happy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_object(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Act
        $response = $this->delete(route('objects.destroy', $object));

        // Assert
        $response->assertRedirect(route('objects.index'));
        $this->assertSoftDeleted('objects', ['id' => $object->id]);
    }

    // -------------------------------------------------------------------------
    // DESTROY — failure
    // -------------------------------------------------------------------------

    public function test_destroy_forbidden_without_delete_objects(): void
    {
        // Arrange — Vedúca has only ViewObjects
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vedúca');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        // Act & Assert
        $this->delete(route('objects.destroy', $object))->assertForbidden();
    }

    public function test_destroy_cross_tenant_object_returns_404(): void
    {
        // Arrange
        $owner = User::factory()->pro()->create(['is_active' => true, 'locale' => 'sk']);
        $tenantA = Tenant::factory()->forOwner($owner)->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenantA);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

        // Act
        $this->delete(route('objects.destroy', $objectB->id))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // DESTROY — edge
    // -------------------------------------------------------------------------

    public function test_already_soft_deleted_object_not_in_scope(): void
    {
        // Arrange
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vlastník');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $object->delete();

        // Act — deleted objects excluded by global scope + SoftDeletes
        $this->get(route('objects.show', $object->id))->assertNotFound();
    }
}
