<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceTemplateEnum;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InvoiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_owner_can_view_invoice_settings_page(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->get(route('settings.invoicing'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Invoicing')
            ->has('settings')
            ->has('templates'),
        );
    }

    public function test_owner_can_update_invoice_settings(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $payload = [
            'invoice_template' => InvoiceTemplateEnum::Modern->value,
            'invoice_number_format' => 'FA-{YYYY}-{XXXX}',
            'iban' => 'SK3112000000198742637541',
            'vat_rate' => 23.0,
            'registration_info' => 'Zapísaná v OR OS Bratislava I, odd. Sro, vl. č. 12345/B',
            'recurring_default_state' => 'draft',
        ];

        $response = $this->put(route('settings.invoicing.update'), $payload);

        $response->assertRedirect(route('invoices.index'));

        $tenant->refresh();
        $this->assertSame('FA-{YYYY}-{XXXX}', $tenant->invoice_number_format);
        $this->assertSame('SK3112000000198742637541', $tenant->iban);
        $this->assertSame(23.0, (float) $tenant->vat_rate);
        $this->assertSame('Zapísaná v OR OS Bratislava I, odd. Sro, vl. č. 12345/B', $tenant->registration_info);
        $this->assertSame(InvoiceTemplateEnum::Modern, $tenant->interface->invoice_template);
    }

    public function test_new_default_template_applied_to_next_invoice(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->put(route('settings.invoicing.update'), [
            'invoice_template' => InvoiceTemplateEnum::Minimal->value,
            'invoice_number_format' => '{YYYY}-{XXXX}',
            'iban' => null,
            'vat_rate' => null,
            'registration_info' => null,
            'recurring_default_state' => 'draft',
        ]);

        $tenant->refresh();
        $this->assertSame(InvoiceTemplateEnum::Minimal, $tenant->interface->invoice_template);
    }

    public function test_registration_info_saved_and_retrievable(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->put(route('settings.invoicing.update'), [
            'invoice_template' => InvoiceTemplateEnum::Classic->value,
            'invoice_number_format' => 'FA-{YYYY}-{XXXXX}',
            'iban' => null,
            'vat_rate' => 23.0,
            'registration_info' => 'Zapísaná v OR OS Košice, odd. Sro, vl. č. 99999/V',
            'recurring_default_state' => 'draft',
        ]);

        $tenant->refresh();
        $this->assertSame('Zapísaná v OR OS Košice, odd. Sro, vl. č. 99999/V', $tenant->registration_info);
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_user_without_manage_billing_settings_gets_403_on_show(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $response = $this->get(route('settings.invoicing'));

        $response->assertForbidden();
    }

    public function test_user_without_manage_billing_settings_gets_403_on_update(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $response = $this->put(route('settings.invoicing.update'), [
            'invoice_template' => InvoiceTemplateEnum::Modern->value,
            'invoice_number_format' => 'FA-{YYYY}-{XXXX}',
        ]);

        $response->assertForbidden();
    }

    public function test_format_without_sequence_placeholder_returns_validation_error(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->put(route('settings.invoicing.update'), [
            'invoice_template' => InvoiceTemplateEnum::Classic->value,
            'invoice_number_format' => 'FA-{YYYY}',
            'iban' => null,
            'vat_rate' => null,
            'registration_info' => null,
            'recurring_default_state' => 'draft',
        ]);

        $response->assertSessionHasErrors('invoice_number_format');
    }

    public function test_invalid_iban_format_returns_validation_error(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->put(route('settings.invoicing.update'), [
            'invoice_template' => InvoiceTemplateEnum::Classic->value,
            'invoice_number_format' => 'FA-{YYYY}-{XXXX}',
            'iban' => 'not-an-iban',
            'vat_rate' => null,
            'registration_info' => null,
            'recurring_default_state' => 'draft',
        ]);

        $response->assertSessionHasErrors('iban');
    }

    public function test_vat_rate_above_100_returns_validation_error(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->put(route('settings.invoicing.update'), [
            'invoice_template' => InvoiceTemplateEnum::Classic->value,
            'invoice_number_format' => 'FA-{YYYY}-{XXXX}',
            'iban' => null,
            'vat_rate' => 150.0,
            'registration_info' => null,
            'recurring_default_state' => 'draft',
        ]);

        $response->assertSessionHasErrors('vat_rate');
    }

    public function test_registration_info_too_long_returns_validation_error(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->put(route('settings.invoicing.update'), [
            'invoice_template' => InvoiceTemplateEnum::Classic->value,
            'invoice_number_format' => 'FA-{YYYY}-{XXXX}',
            'iban' => null,
            'vat_rate' => null,
            'registration_info' => str_repeat('a', 256),
            'recurring_default_state' => 'draft',
        ]);

        $response->assertSessionHasErrors('registration_info');
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function test_null_optional_fields_are_accepted(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->put(route('settings.invoicing.update'), [
            'invoice_template' => InvoiceTemplateEnum::Classic->value,
            'invoice_number_format' => '{YYYY}/{XXX}',
            'iban' => null,
            'vat_rate' => null,
            'registration_info' => null,
            'recurring_default_state' => 'draft',
        ]);

        $response->assertRedirect(route('invoices.index'));
    }

    public function test_vat_rate_zero_is_valid(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->put(route('settings.invoicing.update'), [
            'invoice_template' => InvoiceTemplateEnum::Classic->value,
            'invoice_number_format' => 'FA-{YY}{MM}{XXX}',
            'iban' => null,
            'vat_rate' => 0,
            'registration_info' => null,
            'recurring_default_state' => 'draft',
        ]);

        $tenant->refresh();
        $this->assertSame(0.0, (float) $tenant->vat_rate);
    }

    public function test_unauthenticated_user_redirected_from_settings(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $this->post(route('logout'));

        $response = $this->get(route('settings.invoicing'));

        $response->assertRedirect(route('login'));
    }
}
