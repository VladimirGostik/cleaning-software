<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ClientShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_detail_with_contacts_and_empty_module_arrays(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        ClientContact::factory()->for($client)->create(['tenant_id' => $tenant->id, 'is_primary' => true]);

        // Act
        $response = $this->get(route('clients.show', $client));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Show')
                ->has('client.id')
                ->has('client.contacts', 1)
                ->where('client.objects', [])
                ->where('client.contracts', [])
                ->where('client.invoices', []),
        );
    }

    public function test_show_other_tenant_404(): void
    {
        // Arrange
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->actingAsTenantUser('Vlastník', $tenantA);

        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

        // Act & Assert
        $this->get(route('clients.show', $clientB))->assertNotFound();
    }
}
