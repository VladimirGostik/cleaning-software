<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Models\Client;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class PeppolIdTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Supplier s.r.o.',
            'ico' => '12345678',
            'dic' => null,
            'vat_number' => null,
            'is_vat_payer' => false,
            'address_line' => 'Hlavna 1',
            'city' => 'Bratislava',
            'postal_code' => '811 01',
            'country' => 'SK',
            'contact_email' => 'billing@example.com',
            'contact_phone' => null,
            'invoice_template' => 'classic',
            'invoice_number_format' => 'FA-{YYYY}-{XXXX}',
            'iban' => null,
            'vat_rate' => 20,
            'registration_info' => null,
            'recurring_default_state' => 'draft',
            'swift_bic' => null,
            'default_constant_symbol' => null,
            'default_header_text' => null,
            'default_footer_text' => null,
            'signature_uuid' => null,
            'default_payment_type' => 'transfer',
            'default_currency' => 'EUR',
            'default_rounding_mode' => 'none',
        ], $overrides);
    }

    public function test_valid_peppol_id_is_persisted_and_shown(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['peppol_participant_id' => '0245:12345678']))
            ->assertRedirect(route('settings.invoicing'));

        $this->assertSame('0245:12345678', $tenant->refresh()->peppol_participant_id);
        $this->get(route('settings.invoicing'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('settings.peppol_participant_id', '0245:12345678'),
        );
    }

    public function test_blank_value_is_stored_as_null_and_omitted_field_is_accepted(): void
    {
        $tenant = Tenant::factory()->create(['peppol_participant_id' => '0245:12345678']);
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['peppol_participant_id' => '  ']))
            ->assertRedirect(route('settings.invoicing'));
        $this->assertNull($tenant->refresh()->peppol_participant_id);

        $this->put(route('settings.invoicing.update'), $this->payload())->assertRedirect(route('settings.invoicing'));
    }

    public function test_invalid_format_and_unknown_scheme_are_rejected_and_nothing_written(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        foreach (['abc', '0245-123', '0245:', '0245:a b', '024:123', '0245:'.str_repeat('1', 60)] as $bad) {
            $this->put(route('settings.invoicing.update'), $this->payload(['peppol_participant_id' => $bad]))
                ->assertSessionHasErrors('peppol_participant_id');
        }

        $this->put(route('settings.invoicing.update'), $this->payload(['peppol_participant_id' => '1234:abc']))
            ->assertSessionHasErrors(['peppol_participant_id' => __('app.peppol_participant_scheme_unknown', ['scheme' => '1234'])]);

        $this->assertNull($tenant->refresh()->peppol_participant_id);
    }

    public function test_user_without_manage_billing_settings_is_forbidden(): void
    {
        $this->actingAs($this->userWithPermission('view invoices'));

        $this->put(route('settings.invoicing.update'), $this->payload(['peppol_participant_id' => '0245:12345678']))->assertForbidden();
    }

    public function test_new_invoice_snapshots_supplier_and_client_peppol_ids(): void
    {
        $tenant = Tenant::factory()->create(['peppol_participant_id' => '0245:12345678']);
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'peppol_participant_id' => '9950:SK2020']);

        $invoice = app(InvoiceService::class)->create($this->upsert($client->id));

        $this->assertSame('0245:12345678', $invoice->supplier_peppol_participant_id);
        $this->assertSame('9950:SK2020', $invoice->customer_peppol_participant_id);

        $tenant->update(['peppol_participant_id' => '0245:99999999']);
        $client->update(['peppol_participant_id' => null]);

        $this->assertSame('0245:12345678', $invoice->refresh()->supplier_peppol_participant_id);
        $this->assertSame('9950:SK2020', $invoice->customer_peppol_participant_id);
    }

    public function test_draft_update_resnapshots_and_clientless_invoice_has_no_customer_id(): void
    {
        $tenant = Tenant::factory()->create(['peppol_participant_id' => '0245:12345678']);
        $this->bindTenant($tenant);

        $invoice = app(InvoiceService::class)->create($this->upsert(null));
        $this->assertNull($invoice->customer_peppol_participant_id);

        $tenant->update(['peppol_participant_id' => '0245:77777777']);
        app(InvoiceService::class)->update($invoice, $this->upsert(null));

        $this->assertSame('0245:77777777', $invoice->refresh()->supplier_peppol_participant_id);
    }

    private function upsert(?string $clientId): InvoiceUpsertData
    {
        return InvoiceUpsertData::from([
            'client_id' => $clientId,
            'cleaning_object_id' => null,
            'type' => 'one_off',
            'template' => null,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'period_from' => null,
            'period_to' => null,
            'customer_name' => $clientId === null ? 'Standalone' : null,
            'customer_representative' => null,
            'customer_ico' => null,
            'customer_dic' => null,
            'customer_vat_number' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'customer_country' => null,
            'customer_email' => null,
            'note' => null,
            'items' => [new InvoiceItemData(id: null, description: 'Cleaning', quantity: 1, unit: 'hod', unit_price: 10, discount_percent: 0, vat_rate: 0)],
            'constant_symbol' => null,
            'specific_symbol' => null,
            'header_text' => null,
            'footer_text' => null,
            'deposit' => 0,
            'payment_type' => 'transfer',
            'currency' => 'EUR',
            'rounding_mode' => 'none',
        ]);
    }
}
