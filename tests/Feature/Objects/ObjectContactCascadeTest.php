<?php

declare(strict_types=1);

namespace Tests\Feature\Objects;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Tenant;
use App\Services\ClientService;
use App\Services\ObjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ObjectContactCascadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_delete_soft_deletes_object_contacts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contactIds = $object->contacts()->pluck('id');

        app(ClientService::class)->delete($client);

        $this->assertSoftDeleted('objects', ['id' => $object->id]);

        foreach ($contactIds as $id) {
            $this->assertSoftDeleted('object_contacts', ['id' => $id]);
        }
    }

    public function test_object_deactivate_leaves_contacts_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(1)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contactId = $object->contacts->first()?->id;

        app(ObjectService::class)->deactivate($object);

        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => false]);
        $this->assertDatabaseHas('object_contacts', ['id' => $contactId, 'deleted_at' => null]);
    }

    public function test_object_reactivate_leaves_contacts_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->inactive()->withContacts(1)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contactId = $object->contacts->first()?->id;

        app(ObjectService::class)->reactivate($object);

        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => true]);
        $this->assertDatabaseHas('object_contacts', ['id' => $contactId, 'deleted_at' => null]);
    }

    public function test_soft_deleted_contacts_excluded_from_detail_data(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->withContacts(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $object->contacts()->where('is_primary', false)->firstOrFail()->delete();

        $response = $this->get(route('objects.show', $object));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Objects/Show')->has('object.contacts', 1),
        );
    }
}
