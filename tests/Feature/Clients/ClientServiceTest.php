<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Tenant;
use App\Services\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_soft_deletes_contacts_not_hard_deletes(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'tenant_id' => $tenant->id,
        ]);

        app(ClientService::class)->delete($client);

        $this->assertSoftDeleted('client_contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('client_contacts', ['id' => $contact->id]);
    }

    public function test_delete_soft_deletes_the_client(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        app(ClientService::class)->delete($client);

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_delete_soft_deletes_all_contacts_when_multiple(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->withContacts(3)->create(['tenant_id' => $tenant->id]);

        app(ClientService::class)->delete($client);

        $notDeleted = ClientContact::withoutTrashed()->where('client_id', $client->id)->count();
        $this->assertSame(0, $notDeleted);

        $softDeleted = ClientContact::onlyTrashed()->where('client_id', $client->id)->count();
        $this->assertSame(3, $softDeleted);
    }

    public function test_delete_with_no_contacts_does_not_throw(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        app(ClientService::class)->delete($client);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_http_destroy_soft_deletes_contacts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->delete(route('clients.destroy', $client));

        $this->assertSoftDeleted('client_contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('client_contacts', ['id' => $contact->id]);
    }

    /**
     * D1 (user override): destroy soft-deletes objects — it does not deactivate them.
     */
    public function test_delete_soft_deletes_its_objects(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        app(ClientService::class)->delete($client);

        $this->assertSoftDeleted('objects', ['id' => $object->id]);
        $this->assertDatabaseHas('objects', ['id' => $object->id]);
    }

    public function test_delete_soft_deletes_all_objects_when_multiple(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        app(ClientService::class)->delete($client);

        $notDeleted = CleaningObject::withoutTrashed()->where('client_id', $client->id)->count();
        $this->assertSame(0, $notDeleted);

        $softDeleted = CleaningObject::onlyTrashed()->where('client_id', $client->id)->count();
        $this->assertSame(3, $softDeleted);
    }

    public function test_delete_client_without_objects_does_not_throw(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        app(ClientService::class)->delete($client);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_delete_soft_deletes_both_contacts_and_objects_in_transaction(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->withContacts(2)->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        app(ClientService::class)->delete($client);

        $this->assertSame(0, $client->contacts()->count());
        $this->assertSame(0, CleaningObject::withoutTrashed()->where('client_id', $client->id)->count());
        $this->assertSame(2, CleaningObject::onlyTrashed()->where('client_id', $client->id)->count());
    }

    /** Deactivated objects (direct user action) are also soft-deleted when the client is destroyed. */
    public function test_delete_soft_deletes_already_deactivated_objects_too(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $activeObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $inactiveObject = CleaningObject::factory()->inactive()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        app(ClientService::class)->delete($client);

        $this->assertSoftDeleted('objects', ['id' => $activeObject->id]);
        $this->assertSoftDeleted('objects', ['id' => $inactiveObject->id]);
    }

    /** Objects of other clients are untouched by a client destroy. */
    public function test_delete_leaves_objects_of_other_clients_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $otherClient = Client::factory()->create(['tenant_id' => $tenant->id]);
        $otherObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $otherClient->id]);

        app(ClientService::class)->delete($client);

        $this->assertNotSoftDeleted('objects', ['id' => $otherObject->id]);
    }

    public function test_http_destroy_client_soft_deletes_its_objects(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->delete(route('clients.destroy', $client));

        $this->assertSoftDeleted('objects', ['id' => $object->id]);
        $this->assertDatabaseHas('objects', ['id' => $object->id]);
    }

    /** A deactivated object of a deleted client still resolves `client_name` via `withTrashed()`. */
    public function test_deactivated_object_of_deleted_client_still_resolves_client_name(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ghost Client']);
        $object = CleaningObject::factory()->inactive()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        app(ClientService::class)->delete($client);

        $object->refresh();
        $this->assertSame('Ghost Client', $object->client?->name);
    }
}
