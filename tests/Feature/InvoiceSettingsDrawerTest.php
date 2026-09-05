<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceTemplateEnum;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regression suite for the InvoiceSettingsDrawer addition.
 * Verifies that InvoiceController::index emits the 5 new props the drawer consumes,
 * and that the existing PUT /settings/invoicing route correctly persists those props.
 */
final class InvoiceSettingsDrawerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // 1. index props — new settings props present on Invoices/Index
    // -------------------------------------------------------------------------

    public function test_index_response_includes_invoice_settings_props(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');

        // Act
        $response = $this->get(route('invoices.index'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoiceSettings.invoice_template')
            ->has('invoiceSettings.invoice_number_format')
            ->has('invoiceSettings.iban')
            ->has('invoiceSettings.vat_rate')
            ->has('invoiceSettings.registration_info')
            ->has('settingsTemplateOptions')
            ->where('settingsIsVatPayer', fn (mixed $v) => is_bool($v))
            ->where('nextNumberPreview', null),
        );
    }

    public function test_index_settings_template_options_is_non_empty_array(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');

        $expectedCount = count(InvoiceTemplateEnum::cases());

        // Act
        $response = $this->get(route('invoices.index'));

        // Assert — drawer template selector must receive all enum options
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('settingsTemplateOptions', $expectedCount),
        );
    }

    // -------------------------------------------------------------------------
    // 2. settingsCompanyName reflects the active tenant name
    // -------------------------------------------------------------------------

    public function test_index_with_settings_props_returns_correct_company_name(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');

        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();

        // Act
        $response = $this->get(route('invoices.index'));

        // Assert — drawer header displays tenant name; mismatch = wrong tenant in context
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->where('settingsCompanyName', $tenant->name),
        );
    }

    public function test_index_settings_is_vat_payer_matches_tenant(): void
    {
        // Arrange — TenantFactory defaults is_vat_payer = true; verify the prop round-trips
        $user = $this->actingAsTenantUser('Admin');

        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();

        // Act
        $response = $this->get(route('invoices.index'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->where('settingsIsVatPayer', $tenant->is_vat_payer),
        );
    }

    // -------------------------------------------------------------------------
    // 3. drawer save — PUT /settings/invoicing persists to Tenant model
    // -------------------------------------------------------------------------

    public function test_settings_drawer_save_updates_settings(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');

        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();

        $payload = [
            'invoice_template' => InvoiceTemplateEnum::Classic->value,
            'invoice_number_format' => 'FA-{YYYY}-{XXXX}',
            'iban' => 'SK0000000000000000000000',
            'vat_rate' => 23,
            'registration_info' => 'Test',
            'recurring_default_state' => 'draft',
        ];

        // Act
        $response = $this->put(route('settings.invoicing.update'), $payload);

        // Assert — redirect confirms no 422; DB state confirms persistence
        $response->assertRedirect();

        $tenant->refresh();
        $this->assertSame('FA-{YYYY}-{XXXX}', $tenant->invoice_number_format);
        $this->assertSame('SK0000000000000000000000', $tenant->iban);
        $this->assertSame(23.0, (float) $tenant->vat_rate);
        $this->assertSame('Test', $tenant->registration_info);
    }

    // -------------------------------------------------------------------------
    // 4. Failure paths — auth gate on index new props
    // -------------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_access_invoice_index(): void
    {
        // Arrange — no actingAs call
        // Act
        $response = $this->get(route('invoices.index'));

        // Assert
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_view_invoices_permission_gets_403_on_index(): void
    {
        // Arrange — Interná upratovačka role has no invoice permissions
        $user = $this->actingAsTenantUser('Interná upratovačka');

        // Act
        $response = $this->get(route('invoices.index'));

        // Assert
        $response->assertForbidden();
    }
}
