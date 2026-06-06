<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Tenant;
use App\Services\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientServiceDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_soft_deletes_contacts_not_hard_deletes(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'tenant_id' => $tenant->id,
        ]);

        // Act
        app(ClientService::class)->delete($client);

        // Assert — contact row still exists with deleted_at set (soft-deleted, not hard-deleted)
        $this->assertSoftDeleted('client_contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('client_contacts', ['id' => $contact->id]);
    }

    public function test_delete_soft_deletes_the_client(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        app(ClientService::class)->delete($client);

        // Assert
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_delete_soft_deletes_all_contacts_when_multiple(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->withContacts(3)->create(['tenant_id' => $tenant->id]);

        // Act
        app(ClientService::class)->delete($client);

        // Assert — all 3 contacts soft-deleted
        $notDeleted = ClientContact::withoutTrashed()
            ->where('client_id', $client->id)
            ->count();

        $this->assertSame(0, $notDeleted, 'All contacts must be soft-deleted when client is deleted');

        $softDeleted = ClientContact::onlyTrashed()
            ->where('client_id', $client->id)
            ->count();

        $this->assertSame(3, $softDeleted, 'Exactly 3 contacts must exist as soft-deleted rows');
    }

    public function test_delete_with_no_contacts_does_not_throw(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act & Assert — no exception
        app(ClientService::class)->delete($client);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_http_destroy_soft_deletes_contacts(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'tenant_id' => $tenant->id,
        ]);

        // Act
        $this->delete(route('clients.destroy', $client));

        // Assert — contact is soft-deleted, not gone
        $this->assertSoftDeleted('client_contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('client_contacts', ['id' => $contact->id]);
    }
}
