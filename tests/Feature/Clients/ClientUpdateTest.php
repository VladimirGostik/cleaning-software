<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function updatePayload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'type' => $client->type->value,
            'name' => $client->name,
            'ico' => $client->ico,
            'dic' => $client->dic,
            'vat_number' => $client->vat_number,
            'is_vat_payer' => $client->is_vat_payer,
            'street' => $client->street,
            'city' => $client->city,
            'postal_code' => $client->postal_code,
            'country' => $client->country,
            'note' => $client->note,
            'contacts' => [],
        ], $overrides);
    }

    public function test_update_basic_fields_persists_and_logs_activity(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->patch(
            route('clients.update', $client),
            $this->updatePayload($client, ['name' => 'Updated Name Ltd']),
        );

        // Assert
        $response->assertRedirect(route('clients.show', $client));
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Updated Name Ltd']);
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $client->id,
            'subject_type' => Client::class,
        ]);
    }

    public function test_update_adds_new_contact_and_removes_existing(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $existingContact = ClientContact::factory()->for($client)->create(['tenant_id' => $tenant->id]);
        $toKeepContact = ClientContact::factory()->for($client)->create(['tenant_id' => $tenant->id]);

        // Act — only $toKeepContact in payload plus a new one
        $response = $this->patch(route('clients.update', $client), $this->updatePayload($client, [
            'contacts' => [
                ['id' => $toKeepContact->id, 'name' => $toKeepContact->name, 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
                ['id' => null, 'name' => 'New Contact', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
            ],
        ]));

        // Assert
        $response->assertRedirect(route('clients.show', $client));
        $this->assertSoftDeleted('client_contacts', ['id' => $existingContact->id]);
        $this->assertDatabaseHas('client_contacts', ['client_id' => $client->id, 'name' => 'New Contact']);
    }

    public function test_update_changes_primary_contact(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contact1 = ClientContact::factory()->for($client)->create(['tenant_id' => $tenant->id, 'is_primary' => true]);
        $contact2 = ClientContact::factory()->for($client)->create(['tenant_id' => $tenant->id, 'is_primary' => false]);

        // Act
        $this->patch(route('clients.update', $client), $this->updatePayload($client, [
            'contacts' => [
                ['id' => $contact1->id, 'name' => $contact1->name, 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
                ['id' => $contact2->id, 'name' => $contact2->name, 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
            ],
        ]));

        // Assert
        $this->assertDatabaseHas('client_contacts', ['id' => $contact1->id, 'is_primary' => false]);
        $this->assertDatabaseHas('client_contacts', ['id' => $contact2->id, 'is_primary' => true]);
    }

    public function test_update_other_tenant_client_returns_404(): void
    {
        // Arrange
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->actingAsTenantUser('Admin', $tenantA);

        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

        // Act & Assert
        $this->patch(route('clients.update', $clientB), $this->updatePayload($clientB))->assertNotFound();
    }
}
