<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Models\TenantInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class InvoiceMailSettingsTest extends TestCase
{
    use RefreshDatabase;

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
            'address_line' => 'Hlavná 1',
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

    public function test_update_persists_and_show_returns_mail_texts_and_catalog(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload([
            'invoice_mail_subject' => 'Faktúra {{invoice.number}}',
            'invoice_mail_body' => "Dobrý deň\n{{customer.name}}",
        ]))->assertRedirect(route('settings.invoicing'));

        $interface = TenantInterface::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('Faktúra {{invoice.number}}', $interface->invoice_mail_subject);
        $this->assertSame("Dobrý deň\n{{customer.name}}", $interface->invoice_mail_body);

        $this->get(route('settings.invoicing'))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('settings.invoice_mail_subject', 'Faktúra {{invoice.number}}')
                ->where('settings.invoice_mail_body', "Dobrý deň\n{{customer.name}}")
                ->has('mailPlaceholders.tokens', 12)
                ->where('mailPlaceholders.tokens.0.token', '{{invoice.number}}')
                ->where('mailPlaceholders.tokens.0.label', __('app.invoice_mail_token_invoice_number')),
        );
    }

    public function test_blank_values_are_stored_as_null(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload([
            'invoice_mail_subject' => '  ',
            'invoice_mail_body' => '',
        ]))->assertRedirect(route('settings.invoicing'));

        $interface = TenantInterface::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNull($interface->invoice_mail_subject);
        $this->assertNull($interface->invoice_mail_body);
    }

    public function test_omitted_fields_are_accepted(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload())->assertRedirect(route('settings.invoicing'));
    }

    public function test_limits_enforced_and_nothing_written(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload([
            'name' => 'Should Not Persist',
            'invoice_mail_subject' => str_repeat('a', 256),
            'invoice_mail_body' => str_repeat('b', 5001),
        ]))->assertSessionHasErrors(['invoice_mail_subject', 'invoice_mail_body']);

        $this->assertNotSame('Should Not Persist', $tenant->fresh()?->name);
    }

    public function test_limit_boundaries_pass(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload([
            'invoice_mail_subject' => str_repeat('a', 255),
            'invoice_mail_body' => str_repeat('b', 5000),
        ]))->assertSessionHasNoErrors();
    }

    public function test_user_without_billing_permission_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['invoice_mail_subject' => 'x']))->assertForbidden();
        $this->get(route('settings.invoicing'))->assertForbidden();
    }
}
