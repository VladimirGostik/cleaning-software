<?php

declare(strict_types=1);

namespace Tests\Feature\Objects;

use App\Enums\ObjectTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Role;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ObjectContactVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @return array<string, mixed>
     */
    private function storePayload(string $clientId, array $contacts = []): array
    {
        return [
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
            'contacts' => $contacts,
        ];
    }

    /**
     * `ViewAllObjects` is granted to "Sekretárka" via the role (not directly on the user), so
     * revoking it must happen on the role — `$user->revokePermissionTo(...)` only detaches a
     * direct grant and is a no-op here.
     */
    private function revokeViewAllObjectsFromSekretarka(Tenant $tenant): void
    {
        $role = Role::inTenant($tenant->id)->where('name', 'Sekretárka')->firstOrFail();
        $role->revokePermissionTo(PermissionEnum::ViewAllObjects->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_actor_with_view_all_objects_sees_contacts_on_detail(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(1)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('objects.show', $object));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Objects/Show')->has('object.contacts', 1),
        );
    }

    public function test_own_only_cleaner_with_assigned_job_sees_object_but_contacts_is_null(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(1)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $object->contacts->first()?->update(['email' => 'secret-contact@example.test']);
        ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('objects.show', $object));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Objects/Show')->where('object.contacts', null),
        );
        $response->assertDontSee('secret-contact@example.test');
    }

    public function test_own_only_cleaner_index_returns_null_contact_columns(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(1)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('objects.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Objects/Index')
                ->has('objects.data', 1)
                ->where('objects.data.0.contacts_count', null)
                ->where('objects.data.0.primary_contact_email', null)
                ->where('objects.data.0.primary_contact_phone', null),
        );
    }

    public function test_permitted_actor_with_zero_contacts_gets_empty_array_not_null(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('objects.show', $object));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Objects/Show')->where('object.contacts', []),
        );
    }

    public function test_permitted_actor_with_zero_contacts_gets_contacts_count_zero_not_null(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('objects.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Objects/Index')->where('objects.data.0.contacts_count', 0),
        );
    }

    /**
     * `CreateObjects` does not require an existing-object visibility check (there is no
     * instance yet), so a "Sekretárka" actor with `ViewAllObjects` revoked can still reach
     * `store` — the write-side gate must still ignore the submitted `contacts` payload.
     */
    public function test_own_only_actor_cannot_create_contacts_via_store_payload(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);
        $this->revokeViewAllObjectsFromSekretarka($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $payload = $this->storePayload($client->id, [
            ['id' => null, 'name' => 'Sneaky Contact', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
        ]);

        $this->post(route('objects.store'), $payload)->assertRedirect();

        $object = CleaningObject::where('name', 'Test Office')->firstOrFail();
        $this->assertDatabaseCount('object_contacts', 0);
        $this->assertCount(0, $object->contacts);
    }

    public function test_own_only_actor_update_does_not_wipe_existing_contacts(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Sekretárka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(1)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->revokeViewAllObjectsFromSekretarka($tenant);

        // Payload omits `contacts` entirely — the actor's form never showed them.
        $this->put(route('objects.update', $object), $this->storePayload($client->id))->assertRedirect();

        $this->assertDatabaseCount('object_contacts', 1);
        $this->assertNotSoftDeleted('object_contacts', ['cleaning_object_id' => $object->id]);
    }

    public function test_cross_tenant_object_contacts_not_visible(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);

        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->withContacts(1)->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

        $this->get(route('objects.show', $objectB->id))->assertNotFound();
    }
}
