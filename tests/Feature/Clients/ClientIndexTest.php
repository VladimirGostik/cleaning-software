<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Enums\ClientType;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ClientIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function minimalStorePayload(): array
    {
        return [
            'type' => ClientType::Corporate->value,
            'name' => 'Test Corp',
            'ico' => '12345678',
            'dic' => null,
            'vat_number' => null,
            'is_vat_payer' => false,
            'email' => null,
            'phone' => null,
            'street' => null,
            'city' => null,
            'postal_code' => null,
            'country' => 'SK',
            'note' => null,
            'contacts' => [],
        ];
    }

    public function test_owner_sees_clients_for_active_tenant_only(): void
    {
        // Arrange
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->actingAsTenantUser('Vlastník', $tenantA);

        Client::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
        Client::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

        // Act
        $response = $this->get(route('clients.index'));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 3),
        );
    }

    public function test_search_filter_returns_matching_only(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenant);

        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Foo Company']);
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bar Corp']);
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Another Ltd']);

        // Act
        $response = $this->get(route('clients.index', ['filter[search]' => 'Foo']));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 1),
        );
    }

    public function test_type_filter_returns_only_corporate(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenant);

        Client::factory()->count(3)->create(['tenant_id' => $tenant->id, 'type' => ClientType::Corporate]);
        Client::factory()->count(2)->private()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('clients.index', ['filter[type]' => 'corporate']));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 3),
        );
    }

    public function test_pagination_default_20_per_page(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vlastník', $tenant);

        Client::factory()->count(25)->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('clients.index'));

        // Assert
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 20)
                ->where('clients.meta.total', 25),
        );
    }

    public function test_accountant_can_view_index_but_not_create(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);

        // Act & Assert
        $this->get(route('clients.index'))->assertOk();

        $this->post(route('clients.store'), $this->minimalStorePayload())->assertForbidden();
    }

    public function test_cleaner_forbidden(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Upratovačka', $tenant);

        // Act & Assert
        $this->get(route('clients.index'))->assertForbidden();
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        // Act & Assert
        $this->get(route('clients.index'))->assertRedirect(route('login'));
    }
}
