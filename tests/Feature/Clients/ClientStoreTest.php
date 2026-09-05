<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Enums\ClientTypeEnum;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
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
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload([
            'contacts' => [
                ['id' => null, 'name' => 'Jan Novak', 'position' => 'CEO', 'email' => 'jan@testcorp.sk', 'phone' => null, 'is_primary' => true],
            ],
        ]);

        // Act
        $response = $this->post(route('clients.store'), $payload);

        // Assert
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
        // Arrange
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

        // Act
        $response = $this->post(route('clients.store'), $payload);

        // Assert
        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', ['name' => 'Jana Nováková', 'type' => 'private']);
    }

    public function test_store_corporate_without_ico_fails_validation(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload(['ico' => null]);

        // Act
        $response = $this->post(route('clients.store'), $payload);

        // Assert
        $response->assertSessionHasErrors('ico');
    }

    public function test_store_with_contacts_array_exactly_one_primary(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload([
            'contacts' => [
                ['id' => null, 'name' => 'Contact 1', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => true],
                ['id' => null, 'name' => 'Contact 2', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
                ['id' => null, 'name' => 'Contact 3', 'position' => null, 'email' => null, 'phone' => null, 'is_primary' => false],
            ],
        ]);

        // Act
        $this->post(route('clients.store'), $payload)->assertRedirect();

        // Assert
        $client = Client::where('ico', '12345678')->firstOrFail();
        $this->assertCount(3, $client->contacts);
        $this->assertCount(1, $client->contacts->where('is_primary', true));
    }

    public function test_store_duplicate_ico_in_same_tenant_fails(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        Client::factory()->create(['tenant_id' => $tenant->id, 'ico' => '99999999']);

        $payload = $this->corporatePayload(['ico' => '99999999']);

        // Act
        $response = $this->post(route('clients.store'), $payload);

        // Assert
        $response->assertSessionHasErrors('ico');
    }

    public function test_store_duplicate_ico_across_tenants_succeeds(): void
    {
        // Arrange
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Client::factory()->create(['tenant_id' => $tenantA->id, 'ico' => '88888888']);

        $this->actingAsTenantUser('Admin', $tenantB);

        $payload = $this->corporatePayload(['ico' => '88888888']);

        // Act
        $response = $this->post(route('clients.store'), $payload);

        // Assert
        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_secretary_can_create(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);

        // Act
        $response = $this->post(route('clients.store'), $this->corporatePayload());

        // Assert
        $response->assertRedirect(route('clients.index'));
    }

    public function test_accountant_cannot_create_403(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);

        // Act & Assert
        $this->post(route('clients.store'), $this->corporatePayload())->assertForbidden();
    }

    /** @test regression: country field accepts full country name (was limited to 2 chars by Size(2)) */
    public function test_store_country_full_name_accepted(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload(['country' => 'Slovensko', 'ico' => '11111111']);

        // Act
        $response = $this->post(route('clients.store'), $payload);

        // Assert — must not produce 422; country accepted as string up to 255 chars
        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', [
            'ico' => '11111111',
            'country' => 'Slovensko',
            'tenant_id' => $tenant->id,
        ]);
    }

    /** @test regression: 2-char ISO code still accepted after removing Size(2) */
    public function test_store_country_iso_code_still_accepted(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->corporatePayload(['country' => 'SK', 'ico' => '22222222']);

        // Act
        $response = $this->post(route('clients.store'), $payload);

        // Assert
        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', [
            'ico' => '22222222',
            'country' => 'SK',
            'tenant_id' => $tenant->id,
        ]);
    }
}
