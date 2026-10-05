<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class InvoiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Updated Supplier s.r.o.',
            'ico' => '12345678',
            'dic' => '2012345678',
            'vat_number' => 'SK2012345678',
            'is_vat_payer' => true,
            'address_line' => 'Hlavná 1',
            'city' => 'Bratislava',
            'postal_code' => '811 01',
            'country' => 'SK',
            'contact_email' => 'billing@example.com',
            'contact_phone' => '+421900000000',
            'invoice_template' => 'modern',
            'invoice_number_format' => 'FA-{YYYY}-{XXXX}',
            'iban' => 'SK8975000000000123456789',
            'vat_rate' => 20,
            'registration_info' => 'Registered in Bratislava',
            'recurring_default_state' => 'issued',
            'swift_bic' => 'TATRSKBX',
            'default_constant_symbol' => '0308',
            'default_header_text' => null,
            'default_footer_text' => null,
            'signature_uuid' => null,
            'default_payment_type' => 'transfer',
            'default_currency' => 'EUR',
            'default_rounding_mode' => 'none',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function invoicePayload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => null,
            'cleaning_object_id' => null,
            'type' => 'one_off',
            'template' => null,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'period_from' => null,
            'period_to' => null,
            'customer_name' => 'Template Test',
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
            'items' => [['id' => null, 'description' => 'Item', 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0]],
            'constant_symbol' => null,
            'specific_symbol' => null,
            'header_text' => null,
            'footer_text' => null,
            'deposit' => 0,
            'payment_type' => 'transfer',
            'currency' => 'EUR',
            'rounding_mode' => 'none',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_show_returns_current_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('settings.invoicing'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Settings/Invoicing')
                ->where('settings.name', $tenant->name),
        );
    }

    public function test_update_persists_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload())
            ->assertRedirect(route('settings.invoicing'));

        $tenant->refresh();
        $tenant->load('interface');
        $interface = $tenant->interface;
        $this->assertNotNull($interface);
        $this->assertSame('Updated Supplier s.r.o.', $tenant->name);
        $this->assertSame('SK8975000000000123456789', $tenant->iban);
        $this->assertSame('modern', $interface->invoice_template->value);
        $this->assertSame('issued', $interface->recurring_default_state->value);
    }

    public function test_supplier_fields_round_trip(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload([
            'ico' => '99998888',
            'dic' => '2099998888',
            'address_line' => 'Nová 5',
            'contact_email' => 'new@example.com',
        ]));

        $tenant->refresh();
        $this->assertSame('99998888', $tenant->ico);
        $this->assertSame('2099998888', $tenant->dic);
        $this->assertSame('Nová 5', $tenant->address_line);
        $this->assertSame('new@example.com', $tenant->contact_email);
    }

    public function test_recurring_default_state_persists_on_interface(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['recurring_default_state' => 'draft']));

        $interface = TenantInterface::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('draft', $interface->recurring_default_state->value);
    }

    public function test_updated_template_default_applies_to_next_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $this->put(route('settings.invoicing.update'), $this->payload(['invoice_template' => 'minimal']));

        $response = $this->post(route('invoices.store'), $this->invoicePayload());

        $response->assertRedirect();
        $invoice = Invoice::where('customer_name', 'Template Test')->firstOrFail();
        $this->assertSame('minimal', $invoice->template->value);
    }

    public function test_update_persists_default_header_and_footer_text(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload([
            'default_header_text' => 'Ďakujeme za dôveru',
            'default_footer_text' => 'Splatnosť 14 dní',
        ]))->assertRedirect(route('settings.invoicing'));

        $interface = TenantInterface::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('Ďakujeme za dôveru', $interface->default_header_text);
        $this->assertSame('Splatnosť 14 dní', $interface->default_footer_text);

        $activity = Activity::query()
            ->where('subject_type', (new TenantInterface)->getMorphClass())
            ->where('subject_id', $interface->id)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();
        $changes = $activity->attributeChangesArray();
        $this->assertIsArray($changes);
        $attributes = $changes['attributes'] ?? null;
        $this->assertIsArray($attributes);
        $this->assertArrayHasKey('default_header_text', $attributes);
        $this->assertArrayHasKey('default_footer_text', $attributes);
    }

    public function test_show_exposes_default_header_and_footer_text(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $this->put(route('settings.invoicing.update'), $this->payload([
            'default_header_text' => 'Ďakujeme za dôveru',
            'default_footer_text' => 'Splatnosť 14 dní',
        ]));

        $response = $this->get(route('settings.invoicing'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Settings/Invoicing')
                ->where('settings.default_header_text', 'Ďakujeme za dôveru')
                ->where('settings.default_footer_text', 'Splatnosť 14 dní'),
        );
    }

    public function test_updated_default_texts_do_not_affect_existing_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->post(route('invoices.store'), $this->invoicePayload(['header_text' => 'A', 'footer_text' => 'A footer']));
        $invoice = Invoice::where('customer_name', 'Template Test')->firstOrFail();

        $this->put(route('settings.invoicing.update'), $this->payload([
            'default_header_text' => 'B',
            'default_footer_text' => 'B footer',
        ]));

        $response = $this->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('invoice.header_text', 'A')
                ->where('invoice.footer_text', 'A footer'),
        );
        $invoice->refresh();
        $this->assertSame('A', $invoice->header_text);
        $this->assertSame('A footer', $invoice->footer_text);
    }

    // -------------------------------------------------------------------------
    // failure
    // -------------------------------------------------------------------------

    public function test_show_forbidden_without_manage_billing_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->get(route('settings.invoicing'))->assertForbidden();
    }

    public function test_update_forbidden_without_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload())->assertForbidden();
    }

    public function test_number_format_without_placeholder_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['invoice_number_format' => 'FA-YYYY']))
            ->assertSessionHasErrors('invoice_number_format');
    }

    public function test_invalid_iban_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['iban' => 'not-an-iban']))
            ->assertSessionHasErrors('iban');
    }

    public function test_invalid_swift_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['swift_bic' => 'bad-swift']))
            ->assertSessionHasErrors('swift_bic');
    }

    public function test_vat_rate_over_100_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['vat_rate' => 150]))
            ->assertSessionHasErrors('vat_rate');
    }

    public function test_registration_info_over_255_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['registration_info' => str_repeat('a', 256)]))
            ->assertSessionHasErrors('registration_info');
    }

    public function test_constant_symbol_non_digits_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['default_constant_symbol' => 'abcd']))
            ->assertSessionHasErrors('default_constant_symbol');
    }

    public function test_invalid_enum_value_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['default_payment_type' => 'bitcoin']))
            ->assertSessionHasErrors('default_payment_type');
    }

    public function test_default_header_text_over_1000_chars_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['default_header_text' => str_repeat('a', 1001)]))
            ->assertSessionHasErrors('default_header_text');
    }

    public function test_default_footer_text_over_1000_chars_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['default_footer_text' => str_repeat('a', 1001)]))
            ->assertSessionHasErrors('default_footer_text');
    }

    // -------------------------------------------------------------------------
    // edge
    // -------------------------------------------------------------------------

    public function test_empty_string_default_header_text_stored_as_null(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['default_header_text' => '']))
            ->assertRedirect(route('settings.invoicing'));

        $interface = TenantInterface::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNull($interface->default_header_text);
    }
}
