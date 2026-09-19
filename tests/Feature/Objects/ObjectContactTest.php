<?php

declare(strict_types=1);

namespace Tests\Feature\Objects;

use App\Enums\ObjectTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ObjectContact;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ObjectContactTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePayload(string $clientId, array $contacts = [], array $overrides = []): array
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
            'contacts' => $contacts,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_store_creates_object_with_contacts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $payload = $this->storePayload($client->id, [
            ['id' => null, 'name' => 'Domovník', 'position' => 'Recepcia', 'email' => 'recepcia@test.sk', 'phone' => null, 'is_primary' => true],
        ]);

        $this->post(route('objects.store'), $payload)->assertRedirect();

        $object = CleaningObject::where('name', 'Test Office')->firstOrFail();
        $this->assertDatabaseHas('object_contacts', [
            'cleaning_object_id' => $object->id,
            'name' => 'Domovník',
            'is_primary' => true,
        ]);
    }

    public function test_store_promotes_first_contact_to_primary_when_none_marked(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $payload = $this->storePayload($client->id, [
            ['id' => null, 'name' => 'Contact 1', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
            ['id' => null, 'name' => 'Contact 2', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
        ]);

        $this->post(route('objects.store'), $payload)->assertRedirect();

        $object = CleaningObject::where('name', 'Test Office')->firstOrFail();
        $this->assertCount(1, $object->contacts->where('is_primary', true));
        $this->assertSame('Contact 1', $object->contacts->firstWhere('is_primary', true)?->name);
    }

    public function test_store_persists_contact_tenant_id_from_bound_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $payload = $this->storePayload($client->id, [
            ['id' => null, 'name' => 'Contact 1', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
        ]);

        $this->post(route('objects.store'), $payload)->assertRedirect();

        $object = CleaningObject::where('name', 'Test Office')->firstOrFail();
        $this->assertDatabaseHas('object_contacts', [
            'cleaning_object_id' => $object->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_update_adds_new_contact(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(1)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $existing = $object->contacts()->firstOrFail();

        $payload = $this->storePayload($client->id, [
            ['id' => $existing->id, 'name' => $existing->name, 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
            ['id' => null, 'name' => 'New Contact', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
        ]);

        $this->put(route('objects.update', $object), $payload)->assertRedirect();

        $this->assertSame(2, $object->contacts()->count());
        $this->assertDatabaseHas('object_contacts', ['cleaning_object_id' => $object->id, 'name' => 'New Contact']);
    }

    public function test_update_switches_primary_contact(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $first = $object->contacts()->where('is_primary', true)->firstOrFail();
        $second = $object->contacts()->where('is_primary', false)->firstOrFail();

        $payload = $this->storePayload($client->id, [
            ['id' => $first->id, 'name' => $first->name, 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
            ['id' => $second->id, 'name' => $second->name, 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
        ]);

        $this->put(route('objects.update', $object), $payload)->assertRedirect();

        $this->assertDatabaseHas('object_contacts', ['id' => $second->id, 'is_primary' => true]);
        $this->assertDatabaseHas('object_contacts', ['id' => $first->id, 'is_primary' => false]);
    }

    public function test_update_soft_deletes_removed_contact(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $kept = $object->contacts()->where('is_primary', true)->firstOrFail();
        $removed = $object->contacts()->where('is_primary', false)->firstOrFail();

        $payload = $this->storePayload($client->id, [
            ['id' => $kept->id, 'name' => $kept->name, 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
        ]);

        $this->put(route('objects.update', $object), $payload)->assertRedirect();

        $this->assertSoftDeleted('object_contacts', ['id' => $removed->id]);
        $this->assertDatabaseHas('object_contacts', ['id' => $kept->id, 'deleted_at' => null]);
    }

    public function test_show_returns_contacts_in_detail_data(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(1)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('objects.show', $object));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Objects/Show')
                ->has('object.contacts', 1),
        );
    }

    public function test_index_returns_contacts_count_and_primary_contact_columns(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->withContacts(1)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('objects.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Objects/Index')
                ->where('objects.data.0.contacts_count', 1)
                ->has('objects.data.0.primary_contact_email'),
        );
    }

    public function test_contact_creation_is_activity_logged(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $payload = $this->storePayload($client->id, [
            ['id' => null, 'name' => 'Domovník', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
        ]);

        $this->post(route('objects.store'), $payload);

        $object = CleaningObject::where('name', 'Test Office')->firstOrFail();
        $contact = $object->contacts->firstOrFail();

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $contact->id,
            'subject_type' => ObjectContact::class,
        ]);
    }

    // -------------------------------------------------------------------------
    // failure
    // -------------------------------------------------------------------------

    public function test_store_rejects_multiple_primary_contacts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $payload = $this->storePayload($client->id, [
            ['id' => null, 'name' => 'Contact 1', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
            ['id' => null, 'name' => 'Contact 2', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
        ]);

        $response = $this->post(route('objects.store'), $payload);

        $response->assertSessionHasErrors('contacts');
        $this->assertDatabaseMissing('objects', ['name' => 'Test Office']);
    }

    public function test_update_rejects_contact_id_from_another_object(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $objectA = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $objectB = CleaningObject::factory()->withContacts(1)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $foreignContact = $objectB->contacts()->firstOrFail();

        $payload = $this->storePayload($client->id, [
            ['id' => $foreignContact->id, 'name' => $foreignContact->name, 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
        ]);

        $response = $this->put(route('objects.update', $objectA), $payload);

        $response->assertSessionHasErrors('contacts');
        /** @var ViewErrorBag $errors */
        $errors = session('errors');
        $this->assertSame(__('app.object_contact_invalid'), $errors->getBag('default')->first('contacts'));
    }

    public function test_update_rejects_client_contact_id_in_object_payload(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $clientContact = ClientContact::factory()->for($client)->create(['tenant_id' => $tenant->id]);

        $payload = $this->storePayload($client->id, [
            ['id' => $clientContact->id, 'name' => $clientContact->name, 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
        ]);

        $response = $this->put(route('objects.update', $object), $payload);

        $response->assertSessionHasErrors('contacts');
    }

    public function test_store_contact_name_required(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $payload = $this->storePayload($client->id, [
            ['id' => null, 'name' => '', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
        ]);

        $this->post(route('objects.store'), $payload)->assertSessionHasErrors('contacts.0.name');
    }

    public function test_store_contact_email_must_be_valid(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $payload = $this->storePayload($client->id, [
            ['id' => null, 'name' => 'Contact 1', 'position' => null, 'email' => 'not-an-email', 'phone' => null, 'is_primary' => false],
        ]);

        $this->post(route('objects.store'), $payload)->assertSessionHasErrors('contacts.0.email');
    }

    public function test_store_forbidden_without_create_objects(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vedúca', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $payload = $this->storePayload($client->id, [
            ['id' => null, 'name' => 'Contact 1', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
        ]);

        $this->post(route('objects.store'), $payload)->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // edge
    // -------------------------------------------------------------------------

    public function test_store_accepts_empty_contacts_array(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('objects.store'), $this->storePayload($client->id));

        $response->assertRedirect();
        $object = CleaningObject::where('name', 'Test Office')->firstOrFail();
        $this->assertCount(0, $object->contacts);
    }

    public function test_update_clearing_all_contacts_soft_deletes_every_row(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contactIds = $object->contacts()->pluck('id');

        $this->put(route('objects.update', $object), $this->storePayload($client->id))->assertRedirect();

        foreach ($contactIds as $id) {
            $this->assertSoftDeleted('object_contacts', ['id' => $id]);
        }
    }

    public function test_contact_name_max_255_accepted_256_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $accepted = $this->storePayload($client->id, [
            ['id' => null, 'name' => str_repeat('a', 255), 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
        ]);
        $this->post(route('objects.store'), $accepted)->assertRedirect();

        $rejected = $this->storePayload($client->id, [
            ['id' => null, 'name' => str_repeat('b', 256), 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
        ], ['name' => 'Second Office']);
        $this->post(route('objects.store'), $rejected)->assertSessionHasErrors('contacts.0.name');
    }

    public function test_contact_phone_max_64(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $rejected = $this->storePayload($client->id, [
            ['id' => null, 'name' => 'Contact 1', 'position' => null, 'email' => null, 'phone' => str_repeat('1', 65), 'is_primary' => true],
        ]);

        $this->post(route('objects.store'), $rejected)->assertSessionHasErrors('contacts.0.phone');
    }
}
