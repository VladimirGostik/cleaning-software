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
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->delete(route('clients.destroy', $client));

        $response->assertRedirect(route('clients.index'));
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_soft_deleted_client_returns_404_on_show(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $client->delete();

        $this->get(route('clients.show', $client))->assertNotFound();
    }

    public function test_secretary_can_delete(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->delete(route('clients.destroy', $client));

        $response->assertRedirect(route('clients.index'));
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_accountant_cannot_delete_403(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete(route('clients.destroy', $client))->assertForbidden();
    }
}
