<?php

declare(strict_types=1);

namespace Tests\Feature\Objects;

use App\Enums\ObjectTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ObjectControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a fresh tenant and authenticate the given role inside it.
     */
    private function actingAsTenantUserWithObjectsFeature(string $roleName): Tenant
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
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
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenantA = Tenant::factory()->forOwner($owner)->create();
        $tenantB = Tenant::factory()->create();

        $this->actingAsTenantUser('Admin', $tenantA);

        $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenantA->id, 'client_id' => $clientA->id]);
        CleaningObject::factory()->count(2)->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

        $response = $this->get(route('objects.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Objects/Index')
                ->has('objects.data', 3),
        );
    }

    public function test_index_requires_view_objects_permission_and_passes(): void
    {
        $this->actingAsTenantUserWithObjectsFeature('Interná upratovačka');

        $this->get(route('objects.index'))->assertOk();
    }

    public function test_index_redirects_unauthenticated(): void
    {
        $this->get(route('objects.index'))->assertRedirect(route('login'));
    }

    public function test_index_filter_by_type(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        CleaningObject::factory()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'type' => ObjectTypeEnum::Office]);
        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'type' => ObjectTypeEnum::Apartment]);

        $response = $this->get(route('objects.index', ['filter[type]' => 'office']));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Objects/Index')->has('objects.data', 2),
        );
    }

    public function test_index_filter_by_client_id(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);

        CleaningObject::factory()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $clientA->id]);
        CleaningObject::factory()->count(4)->create(['tenant_id' => $tenant->id, 'client_id' => $clientB->id]);

        $response = $this->get(route('objects.index', ['filter[client_id]' => $clientA->id]));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Objects/Index')->has('objects.data', 2),
        );
    }

    public function test_index_filter_by_is_active(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);
        CleaningObject::factory()->inactive()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('objects.index', ['filter[is_active]' => '0']));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Objects/Index')->has('objects.data', 2),
        );
    }

    public function test_index_filter_is_active_false_returns_inactive_only(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true, 'name' => 'Active One']);
        CleaningObject::factory()->inactive()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'name' => 'Inactive One']);

        $response = $this->get(route('objects.index', ['filter[is_active]' => '0']));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Objects/Index')
                ->has('objects.data', 1)
                ->where('objects.data.0.name', 'Inactive One'),
        );
    }

    public function test_index_filter_by_search(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'name' => 'Alpha Office']);
        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'name' => 'Beta Apartment']);

        $response = $this->get(route('objects.index', ['filter[search]' => 'Alpha']));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Objects/Index')->has('objects.data', 1),
        );
    }

    public function test_index_empty_result_set(): void
    {
        $this->actingAsTenantUserWithObjectsFeature('Admin');

        $response = $this->get(route('objects.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Objects/Index')->has('objects.data', 0),
        );
    }

    public function test_index_per_page_boundary_10(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(15)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('objects.index', ['per_page' => 10]));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Objects/Index')->has('objects.data', 10),
        );
    }

    /** D2 fail-closed: an actor without `view all objects` sees an empty index and no client filter options. */
    public function test_index_hides_all_objects_without_view_all_objects(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Interná upratovačka');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('objects.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Objects/Index')
                ->has('objects.data', 0)
                ->has('filterOptions.clients', 0),
        );
    }

    // -------------------------------------------------------------------------
    // INDEX — failure
    // -------------------------------------------------------------------------

    public function test_index_forbidden_without_view_objects_permission(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $user = auth()->user();
        /** @var User $user */
        $user->syncRoles([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->get(route('objects.index'))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // SHOW — happy
    // -------------------------------------------------------------------------

    public function test_show_returns_object_detail(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('objects.show', $object));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Objects/Show')
            ->where('object.id', $object->id),
        );
    }

    // -------------------------------------------------------------------------
    // SHOW — failure
    // -------------------------------------------------------------------------

    public function test_show_cross_tenant_object_returns_404(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenantA = Tenant::factory()->forOwner($owner)->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);

        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

        $this->get(route('objects.show', $objectB->id))->assertNotFound();
    }

    /** D2 fail-closed: same-tenant actor without `view all objects` is 403'd by the policy. */
    public function test_show_forbidden_for_own_only_actor(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Interná upratovačka');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->get(route('objects.show', $object))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // STORE — happy
    // -------------------------------------------------------------------------

    public function test_store_creates_object_with_tenant_id_and_redirects_to_show(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('objects.store'), $this->storePayload($client->id));

        $object = CleaningObject::where('name', 'Test Office')->firstOrFail();
        $response->assertRedirect(route('objects.show', $object));
        $this->assertDatabaseHas('objects', [
            'name' => 'Test Office',
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_store_activity_logged(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->storePayload($client->id));

        $object = CleaningObject::where('name', 'Test Office')->firstOrFail();
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $object->id,
            'subject_type' => 'cleaning_object',
        ]);
    }

    // -------------------------------------------------------------------------
    // STORE — failure
    // -------------------------------------------------------------------------

    public function test_store_forbidden_without_create_objects(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vedúca');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->storePayload($client->id))->assertForbidden();
    }

    public function test_store_validation_missing_name(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('objects.store'), $this->storePayload($client->id, ['name' => '']));

        $response->assertSessionHasErrors('name');
    }

    public function test_store_validation_missing_type(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('objects.store'), $this->storePayload($client->id, ['type' => '']));

        $response->assertSessionHasErrors('type');
    }

    public function test_store_validation_nonexistent_client_id(): void
    {
        $this->actingAsTenantUserWithObjectsFeature('Admin');

        $response = $this->post(route('objects.store'), $this->storePayload('00000000-0000-0000-0000-000000000000'));

        $response->assertSessionHasErrors('client_id');
    }

    public function test_store_validation_negative_area_sqm(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('objects.store'), $this->storePayload($client->id, ['area_sqm' => -10]));

        $response->assertSessionHasErrors('area_sqm');
    }

    public function test_store_validation_negative_key_count(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('objects.store'), $this->storePayload($client->id, ['key_count' => -1]));

        $response->assertSessionHasErrors('key_count');
    }

    // -------------------------------------------------------------------------
    // STORE — edge
    // -------------------------------------------------------------------------

    public function test_store_nullable_fields_persist_null(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->storePayload($client->id, [
            'name' => 'Nullable Test',
            'street' => null,
            'city' => null,
            'access_code' => null,
            'area_sqm' => null,
        ]));

        $this->assertDatabaseHas('objects', [
            'name' => 'Nullable Test',
            'street' => null,
            'city' => null,
            'access_code' => null,
        ]);
    }

    public function test_store_name_max_255_accepted(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('objects.store'), $this->storePayload($client->id, ['name' => str_repeat('a', 255)]));
        $object = CleaningObject::where('name', str_repeat('a', 255))->firstOrFail();

        $response->assertRedirect(route('objects.show', $object));
    }

    public function test_store_name_over_255_rejected(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->storePayload($client->id, ['name' => str_repeat('b', 256)]))
            ->assertSessionHasErrors('name');
    }

    public function test_store_cross_tenant_client_id_rejected(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenantA = Tenant::factory()->forOwner($owner)->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

        $response = $this->post(route('objects.store'), $this->storePayload($clientB->id));

        $response->assertSessionHasErrors('client_id');
    }

    // -------------------------------------------------------------------------
    // UPDATE — happy
    // -------------------------------------------------------------------------

    public function test_update_modifies_object(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->put(route('objects.update', $object), $this->storePayload($client->id, ['name' => 'Updated Name']));

        $response->assertRedirect(route('objects.show', $object));
        $this->assertDatabaseHas('objects', ['id' => $object->id, 'name' => 'Updated Name']);
    }

    public function test_update_allows_client_id_reassignment(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $clientA->id]);

        $this->put(route('objects.update', $object), $this->storePayload($clientB->id));

        $this->assertDatabaseHas('objects', ['id' => $object->id, 'client_id' => $clientB->id]);
    }

    // -------------------------------------------------------------------------
    // UPDATE — failure
    // -------------------------------------------------------------------------

    public function test_update_forbidden_without_edit_objects(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vedúca');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->put(route('objects.update', $object), $this->storePayload($client->id))->assertForbidden();
    }

    public function test_update_cross_tenant_object_returns_404(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenantA = Tenant::factory()->forOwner($owner)->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

        $this->put(route('objects.update', $objectB->id), $this->storePayload($clientB->id))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // UPDATE — edge
    // -------------------------------------------------------------------------

    public function test_update_toggle_is_active_to_false(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);

        $this->put(route('objects.update', $object), $this->storePayload($client->id, ['is_active' => false]));

        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => false]);
    }

    public function test_update_toggle_is_active_back_to_true(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => false]);

        $this->put(route('objects.update', $object), $this->storePayload($client->id, ['is_active' => true]));

        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => true]);
    }

    // -------------------------------------------------------------------------
    // DEACTIVATE — happy
    // -------------------------------------------------------------------------

    public function test_deactivate_sets_is_active_false(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->post(route('objects.deactivate', $object));

        $response->assertRedirect(route('objects.show', $object));
        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => false]);
        $this->assertNotSoftDeleted('objects', ['id' => $object->id]);
    }

    // -------------------------------------------------------------------------
    // DEACTIVATE — failure
    // -------------------------------------------------------------------------

    public function test_deactivate_forbidden_without_delete_objects(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vedúca');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->post(route('objects.deactivate', $object))->assertForbidden();
    }

    public function test_deactivate_cross_tenant_object_returns_404(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenantA = Tenant::factory()->forOwner($owner)->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

        $this->post(route('objects.deactivate', $objectB->id))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // DEACTIVATE — edge
    // -------------------------------------------------------------------------

    public function test_already_soft_deleted_object_not_in_scope(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $object->delete();

        $this->get(route('objects.show', $object->id))->assertNotFound();
    }

    public function test_deactivate_is_idempotent_on_already_inactive_object(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => false]);

        $response = $this->post(route('objects.deactivate', $object));

        $response->assertRedirect(route('objects.show', $object));
        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => false]);
    }

    public function test_object_can_be_reactivated_via_update(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $this->post(route('objects.deactivate', $object));

        $this->put(route('objects.update', $object), $this->storePayload($client->id, ['is_active' => true]));

        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => true]);
    }

    // -------------------------------------------------------------------------
    // REACTIVATE (dedicated action) — happy / failure / edge
    // -------------------------------------------------------------------------

    public function test_reactivate_sets_is_active_true(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->inactive()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->post(route('objects.reactivate', $object));

        $response->assertRedirect(route('objects.show', $object));
        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => true]);
    }

    public function test_reactivate_forbidden_without_edit_objects(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Vedúca');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->inactive()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->post(route('objects.reactivate', $object))->assertForbidden();
    }

    public function test_reactivate_cross_tenant_object_returns_404(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenantA = Tenant::factory()->forOwner($owner)->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->inactive()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

        $this->post(route('objects.reactivate', $objectB->id))->assertNotFound();
    }

    public function test_reactivate_is_idempotent_on_already_active_object(): void
    {
        $tenant = $this->actingAsTenantUserWithObjectsFeature('Admin');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);

        $response = $this->post(route('objects.reactivate', $object));

        $response->assertRedirect(route('objects.show', $object));
        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => true]);
    }
}
