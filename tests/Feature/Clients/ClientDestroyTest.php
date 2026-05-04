<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_soft_delete_client(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->delete(route('clients.destroy', $client));

        // Assert
        $response->assertRedirect(route('clients.index'));
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_soft_deleted_client_returns_404_on_show(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $client->delete();

        // Act & Assert
        $this->get(route('clients.show', $client))->assertNotFound();
    }

    public function test_secretary_can_delete(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->delete(route('clients.destroy', $client));

        // Assert
        $response->assertRedirect(route('clients.index'));
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_accountant_cannot_delete_403(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Act & Assert
        $this->delete(route('clients.destroy', $client))->assertForbidden();
    }
}
