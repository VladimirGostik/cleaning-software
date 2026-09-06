<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Enums\ClientTypeEnum;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

final class ClientStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function corporatePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => ClientTypeEnum::Corporate->value,
            'name' => 'Test Corp s.r.o.',
            'ico' => '12345678',
            'dic' => '2012345678',
            'vat_number' => 'SK2012345678',
            'is_vat_payer' => true,
            'street' => 'Hlavná 1',
            'city' => 'Bratislava',
            'postal_code' => '811 01',
            'country' => 'SK',
            'note' => null,
            'contacts' => [],
        ], $overrides);
    }

    public function test_store_corporate_with_full_payload_persists_record(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload([
            'contacts' => [
                ['id' => null, 'name' => 'Jan Novak', 'position' => 'CEO', 'email' => 'jan@testcorp.sk', 'phone' => null, 'is_primary' => true],
            ],
        ]);

        $response = $this->post(route('clients.store'), $payload);

        $response->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'name' => 'Test Corp s.r.o.',
            'ico' => '12345678',
            'tenant_id' => $tenant->id,
        ]);

        $client = Client::where('ico', '12345678')->firstOrFail();
        $this->assertDatabaseHas('client_contacts', [
            'client_id' => $client->id,
            'name' => 'Jan Novak',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $client->id,
            'subject_type' => Client::class,
        ]);
    }

    public function test_store_private_without_ico_succeeds(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = [
            'type' => ClientTypeEnum::Private->value,
            'name' => 'Jana Nováková',
            'ico' => null,
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

        $response = $this->post(route('clients.store'), $payload);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', ['name' => 'Jana Nováková', 'type' => 'private']);
    }

    public function test_store_corporate_without_ico_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload(['ico' => null]);

        $response = $this->post(route('clients.store'), $payload);

        $response->assertSessionHasErrors('ico');
    }

    public function test_store_with_contacts_array_exactly_one_primary(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload([
            'contacts' => [
                ['id' => null, 'name' => 'Contact 1', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
                ['id' => null, 'name' => 'Contact 2', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
                ['id' => null, 'name' => 'Contact 3', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
            ],
        ]);

        $this->post(route('clients.store'), $payload)->assertRedirect();

        $client = Client::where('ico', '12345678')->firstOrFail();
        $this->assertCount(3, $client->contacts);
        $this->assertCount(1, $client->contacts->where('is_primary', true));
    }

    public function test_store_with_two_primary_contacts_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload([
            'contacts' => [
                ['id' => null, 'name' => 'Contact 1', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
                ['id' => null, 'name' => 'Contact 2', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
            ],
        ]);

        $response = $this->post(route('clients.store'), $payload);

        $response->assertSessionHasErrors('contacts');
        $this->assertDatabaseMissing('clients', ['ico' => '12345678']);
    }

    public function test_store_contacts_without_primary_promotes_first(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload([
            'contacts' => [
                ['id' => null, 'name' => 'Contact 1', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
                ['id' => null, 'name' => 'Contact 2', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
            ],
        ]);

        $this->post(route('clients.store'), $payload)->assertRedirect();

        $client = Client::where('ico', '12345678')->firstOrFail();
        $this->assertCount(1, $client->contacts->where('is_primary', true));
        $primaryContact = $client->contacts->firstWhere('is_primary', true);
        $this->assertNotNull($primaryContact);
        $this->assertSame('Contact 1', $primaryContact->name);
    }

    public function test_store_duplicate_ico_in_same_tenant_fails(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        Client::factory()->create(['tenant_id' => $tenant->id, 'ico' => '99999999']);

        $payload = $this->corporatePayload(['ico' => '99999999']);

        $response = $this->post(route('clients.store'), $payload);

        $response->assertSessionHasErrors('ico');
    }

    public function test_store_duplicate_ico_across_tenants_succeeds(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Client::factory()->create(['tenant_id' => $tenantA->id, 'ico' => '88888888']);

        $this->actingAsTenantUser('Admin', $tenantB);

        $payload = $this->corporatePayload(['ico' => '88888888']);

        $response = $this->post(route('clients.store'), $payload);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_store_duplicate_ico_message_uses_translated_attribute(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        Client::factory()->create(['tenant_id' => $tenant->id, 'ico' => '99999999']);

        $payload = $this->corporatePayload(['ico' => '99999999']);

        $response = $this->post(route('clients.store'), $payload);

        $response->assertSessionHasErrors('ico');
        /** @var ViewErrorBag $errors */
        $errors = session('errors');
        $message = $errors->getBag('default')->first('ico');

        $this->assertStringContainsString(__('app.client_ico'), $message);
        $this->assertStringNotContainsString('poľa ico', $message);
    }

    /**
     * D8: the partial unique index (WHERE deleted_at IS NULL) and the DTO's
     * `whereNull('deleted_at')` unique rule both permit reusing an IČO once the
     * owning client has been soft-deleted.
     */
    public function test_store_reuses_ico_from_soft_deleted_client(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $deleted = Client::factory()->create(['tenant_id' => $tenant->id, 'ico' => '55555555']);
        $deleted->delete();

        $payload = $this->corporatePayload(['ico' => '55555555']);

        $response = $this->post(route('clients.store'), $payload);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseCount('clients', 2);
    }

    /** Negative counterpart: the IČO of a live (not soft-deleted) client in the same tenant is not reusable. */
    public function test_store_rejects_ico_of_a_not_deleted_client_in_same_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        Client::factory()->create(['tenant_id' => $tenant->id, 'ico' => '66666666']);

        $payload = $this->corporatePayload(['ico' => '66666666']);

        $response = $this->post(route('clients.store'), $payload);

        $response->assertSessionHasErrors('ico');
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_secretary_can_create(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);

        $response = $this->post(route('clients.store'), $this->corporatePayload());

        $response->assertRedirect(route('clients.index'));
    }

    public function test_accountant_cannot_create_403(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);

        $this->post(route('clients.store'), $this->corporatePayload())->assertForbidden();
    }

    public function test_store_country_full_name_accepted(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload(['country' => 'Slovensko', 'ico' => '11111111']);

        $response = $this->post(route('clients.store'), $payload);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', [
            'ico' => '11111111',
            'country' => 'Slovensko',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_store_country_iso_code_still_accepted(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload(['country' => 'SK', 'ico' => '22222222']);

        $response = $this->post(route('clients.store'), $payload);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', [
            'ico' => '22222222',
            'country' => 'SK',
            'tenant_id' => $tenant->id,
        ]);
    }
}
