<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Enums\ClientTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ClientIndexTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function minimalStorePayload(): array
    {
        return [
            'type' => ClientTypeEnum::Corporate->value,
            'name' => 'Test Corp',
            'ico' => '12345678',
            'dic' => null,
            'vat_number' => null,
            'is_vat_payer' => false,
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
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->actingAsTenantUser('Admin', $tenantA);

        Client::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
        Client::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

        $response = $this->get(route('clients.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 3),
        );
    }

    public function test_search_filter_returns_matching_only(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Foo Company']);
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bar Corp']);
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Another Ltd']);

        $response = $this->get(route('clients.index', ['filter[search]' => 'Foo']));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 1),
        );
    }

    public function test_type_filter_returns_only_corporate(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        Client::factory()->count(3)->create(['tenant_id' => $tenant->id, 'type' => ClientTypeEnum::Corporate]);
        Client::factory()->count(2)->private()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('clients.index', ['filter[type]' => 'corporate']));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 3),
        );
    }

    public function test_type_filter_supports_not_equal_operator(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        Client::factory()->count(3)->create(['tenant_id' => $tenant->id, 'type' => ClientTypeEnum::Corporate]);
        Client::factory()->count(2)->private()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('clients.index', ['filter[type]' => '!=:corporate']));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 2),
        );
    }

    public function test_pagination_default_25_per_page(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        Client::factory()->count(30)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('clients.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 25)
                ->where('clients.total', 30),
        );
    }

    public function test_accountant_can_view_index_but_not_create(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);

        $this->get(route('clients.index'))->assertOk();

        $this->post(route('clients.store'), $this->minimalStorePayload())->assertForbidden();
    }

    public function test_cleaner_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->get(route('clients.index'))->assertForbidden();
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->get(route('clients.index'))->assertRedirect(route('login'));
    }

    public function test_listing_exposes_primary_contact_email_from_primary_contact(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        ClientContact::factory()->for($client)->create([
            'tenant_id' => $tenant->id,
            'email' => 'primary@example.com',
            'phone' => null,
            'is_primary' => true,
        ]);
        ClientContact::factory()->for($client)->create([
            'tenant_id' => $tenant->id,
            'email' => 'other@example.com',
            'phone' => null,
            'is_primary' => false,
        ]);

        $response = $this->get(route('clients.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->where('clients.data.0.primary_contact_email', 'primary@example.com'),
        );
    }

    public function test_listing_exposes_primary_contact_phone_from_primary_contact(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        ClientContact::factory()->for($client)->create([
            'tenant_id' => $tenant->id,
            'email' => null,
            'phone' => '+421900123456',
            'is_primary' => true,
        ]);

        $response = $this->get(route('clients.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->where('clients.data.0.primary_contact_phone', '+421900123456'),
        );
    }

    public function test_listing_primary_contact_fields_are_null_when_no_primary_contact_exists(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        ClientContact::factory()->for($client)->create([
            'tenant_id' => $tenant->id,
            'email' => 'nonprimary@example.com',
            'is_primary' => false,
        ]);

        $response = $this->get(route('clients.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->where('clients.data.0.primary_contact_email', null)
                ->where('clients.data.0.primary_contact_phone', null),
        );
    }

    public function test_objects_count_reflects_actual_cleaning_objects_linked_to_client(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $clientWithObjects = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alpha']);
        CleaningObject::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'client_id' => $clientWithObjects->id,
        ]);

        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Beta']);

        $response = $this->get(route('clients.index'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Clients/Index');

            /** @var array<int, array<string, mixed>> $data */
            $data = data_get($page->toArray(), 'props.clients.data', []);

            $byName = [];
            foreach ($data as $row) {
                /** @var string $name */
                $name = $row['name'];
                $byName[$name] = $row;
            }

            $this->assertSame(2, $byName['Alpha']['objects_count']);
            $this->assertSame(0, $byName['Beta']['objects_count']);
        });
    }

    public function test_objects_count_is_zero_when_no_objects_exist(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('clients.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->where('clients.data.0.objects_count', 0),
        );
    }

    public function test_objects_from_other_tenant_not_counted(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->actingAsTenantUser('Admin', $tenantA);

        Client::factory()->create(['tenant_id' => $tenantA->id]);

        $otherClient = Client::factory()->create(['tenant_id' => $tenantB->id]);
        CleaningObject::factory()->count(3)->create([
            'tenant_id' => $tenantB->id,
            'client_id' => $otherClient->id,
        ]);

        $response = $this->get(route('clients.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->where('clients.data.0.objects_count', 0),
        );
    }
}
