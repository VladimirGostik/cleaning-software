<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Data\Clients\ClientContactData;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Tenant;
use App\Services\ContactCollectionSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ContactCollectionSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<ClientContactData>  $items
     * @return array<int, ClientContactData>
     */
    private function contacts(array $items): array
    {
        return $items;
    }

    public function test_sync_creates_new_contacts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        app(ContactCollectionSynchronizer::class)->sync(
            $client->contacts(),
            $this->contacts([
                new ClientContactData(id: null, name: 'Contact 1', position: null, email: null, phone: null, is_primary: true),
            ]),
            'app.client_contact_invalid',
        );

        $this->assertCount(1, $client->contacts()->get());
        $this->assertDatabaseHas('client_contacts', ['client_id' => $client->id, 'name' => 'Contact 1', 'is_primary' => true]);
    }

    public function test_sync_updates_existing_contacts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $existing = ClientContact::factory()->for($client)->create(['tenant_id' => $tenant->id, 'name' => 'Old Name']);

        app(ContactCollectionSynchronizer::class)->sync(
            $client->contacts(),
            $this->contacts([
                new ClientContactData(id: $existing->id, name: 'New Name', position: null, email: null, phone: null, is_primary: true),
            ]),
            'app.client_contact_invalid',
        );

        $this->assertDatabaseHas('client_contacts', ['id' => $existing->id, 'name' => 'New Name']);
    }

    public function test_sync_soft_deletes_outgoing_contacts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $existing = ClientContact::factory()->for($client)->create(['tenant_id' => $tenant->id, 'is_primary' => true]);

        app(ContactCollectionSynchronizer::class)->sync(
            $client->contacts(),
            $this->contacts([]),
            'app.client_contact_invalid',
        );

        $this->assertSoftDeleted('client_contacts', ['id' => $existing->id]);
    }

    public function test_sync_promotes_first_when_no_primary(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        app(ContactCollectionSynchronizer::class)->sync(
            $client->contacts(),
            $this->contacts([
                new ClientContactData(id: null, name: 'Contact 1', position: null, email: null, phone: null, is_primary: false),
                new ClientContactData(id: null, name: 'Contact 2', position: null, email: null, phone: null, is_primary: false),
            ]),
            'app.client_contact_invalid',
        );

        $this->assertSame(1, $client->contacts()->where('is_primary', true)->count());
        $this->assertSame('Contact 1', $client->contacts()->where('is_primary', true)->firstOrFail()->name);
    }

    public function test_sync_throws_validation_exception_on_foreign_contact_id(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);
        $foreignContact = ClientContact::factory()->for($clientB)->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(ContactCollectionSynchronizer::class)->sync(
            $clientA->contacts(),
            $this->contacts([
                new ClientContactData(id: $foreignContact->id, name: $foreignContact->name, position: null, email: null, phone: null, is_primary: true),
            ]),
            'app.client_contact_invalid',
        );
    }
}
