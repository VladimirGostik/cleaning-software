<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ClientShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_detail_with_contacts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        ClientContact::factory()->for($client)->create(['tenant_id' => $tenant->id, 'is_primary' => true]);

        $response = $this->get(route('clients.show', $client));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Show')
                ->has('client.id')
                ->has('client.contacts', 1)
                ->has('objects', 0),
        );
    }

    public function test_show_returns_objects_for_client(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(2)->for($client)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('clients.show', $client));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Show')
                ->has('objects', 2)
                ->has('objects.0.id')
                ->has('objects.0.name')
                ->has('objects.0.type'),
        );
    }

    public function test_show_other_tenant_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->actingAsTenantUser('Admin', $tenantA);

        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

        $this->get(route('clients.show', $clientB))->assertNotFound();
    }
}
