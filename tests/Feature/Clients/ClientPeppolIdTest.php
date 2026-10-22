<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class ClientPeppolIdTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'corporate',
            'name' => 'Peppol Client s.r.o.',
            'ico' => '12345678',
            'dic' => null,
            'vat_number' => null,
            'is_vat_payer' => false,
            'street' => 'Hlavna 1',
            'city' => 'Bratislava',
            'postal_code' => '811 01',
            'country' => 'SK',
            'note' => null,
            'contacts' => [],
        ], $overrides);
    }

    public function test_store_persists_valid_peppol_id_and_detail_exposes_it(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->post(route('clients.store'), $this->payload(['peppol_participant_id' => '0245:12345678']))->assertRedirect(route('clients.index'));

        $client = Client::query()->where('ico', '12345678')->firstOrFail();
        $this->assertSame('0245:12345678', $client->peppol_participant_id);

        $this->get(route('clients.show', $client))->assertInertia(
            fn (AssertableInertia $page) => $page->where('client.peppol_participant_id', '0245:12345678'),
        );
    }

    public function test_update_changes_and_clears_peppol_id(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'peppol_participant_id' => '0245:11111111']);

        $this->patch(route('clients.update', $client), $this->payload(['ico' => $client->ico, 'peppol_participant_id' => '9950:SK-2020.1']))->assertRedirect();
        $this->assertSame('9950:SK-2020.1', $client->refresh()->peppol_participant_id);

        $this->patch(route('clients.update', $client), $this->payload(['ico' => $client->ico, 'peppol_participant_id' => null]))->assertRedirect();
        $this->assertNull($client->refresh()->peppol_participant_id);
    }

    public function test_invalid_format_unknown_scheme_and_too_long_are_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        foreach (['12345678', '024:123', 'abcd:123', '0245:', '0245:ab cd', '0245:ab_cd', '0245:'.str_repeat('1', 60)] as $bad) {
            $this->post(route('clients.store'), $this->payload(['peppol_participant_id' => $bad]))
                ->assertSessionHasErrors('peppol_participant_id');
        }

        $this->post(route('clients.store'), $this->payload(['peppol_participant_id' => '1234:abc']))
            ->assertSessionHasErrors(['peppol_participant_id' => __('app.peppol_participant_scheme_unknown', ['scheme' => '1234'])]);

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_scheme_list_comes_from_config(): void
    {
        config(['efa.participant_schemes' => ['1234']]);
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->post(route('clients.store'), $this->payload(['peppol_participant_id' => '1234:abc']))->assertRedirect(route('clients.index'));
    }

    public function test_empty_scheme_list_accepts_any_four_digit_scheme(): void
    {
        config(['efa.participant_schemes' => []]);
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->post(route('clients.store'), $this->payload(['peppol_participant_id' => '7777:abc']))->assertRedirect(route('clients.index'));
    }

    public function test_user_without_edit_clients_permission_cannot_set_it(): void
    {
        $this->actingAs($this->userWithPermission('view clients'));
        $client = Client::factory()->create(['tenant_id' => current_tenant_id()]);

        $this->patch(route('clients.update', $client), $this->payload(['ico' => $client->ico, 'peppol_participant_id' => '0245:12345678']))->assertForbidden();
        $this->assertNull($client->refresh()->peppol_participant_id);
    }
}
