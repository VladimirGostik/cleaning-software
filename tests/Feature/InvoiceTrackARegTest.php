<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regression suite for Track A FE-only design alignment.
 * Verifies backend controller/prop contracts were not altered:
 *   - InvoiceController::index — correct component + prop shape + tab/month query accepted
 *   - InvoiceController::create — correct component + all required prop keys
 *   - InvoiceController::show — correct component + full invoice prop shape
 *   - InvoiceSettingsController::show — correct component + required prop keys
 */
final class InvoiceTrackARegTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // index — prop shape + tab/month filter acceptance
    // -------------------------------------------------------------------------

    public function test_index_returns_correct_inertia_component(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act
        $response = $this->get(route('invoices.index'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Invoices/Index'));
    }

    public function test_index_returns_required_prop_keys(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act
        $response = $this->get(route('invoices.index'));

        // Assert — all four keys the FE destructures must be present
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices')
            ->has('filters')
            ->has('statusOptions')
            ->has('typeOptions'),
        );
    }

    public function test_index_filters_prop_has_expected_shape(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act
        $response = $this->get(route('invoices.index'));

        // Assert — InvoiceIndexFilterData serialises these keys
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('filters.search')
            ->has('filters.status')
            ->has('filters.type')
            ->has('filters.client_id')
            ->has('filters.per_page'),
        );
    }

    public function test_index_accepts_status_filter_and_returns_matching_invoices(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        Invoice::factory()->issued()->count(1)->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('invoices.index', ['filter[status]' => InvoiceStatusEnum::Draft->value]));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 2),
        );
    }

    public function test_index_accepts_tab_query_param_without_error(): void
    {
        // Arrange — FE useInvoiceFilters sends tab param; backend ignores unknown keys silently
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act — send all params the composable can produce
        $response = $this->get(route('invoices.index', [
            'tab' => 'drafts',
            'status' => InvoiceStatusEnum::Draft->value,
        ]));

        // Assert — must not 422; filters prop still present
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices')
            ->has('filters'),
        );
    }

    public function test_index_accepts_month_query_param_without_error(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act — FE sends month as 'YYYY-MM' string
        $response = $this->get(route('invoices.index', ['month' => '2025-06']));

        // Assert — must not 422
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Invoices/Index'));
    }

    public function test_index_accepts_tab_and_month_together_without_error(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act
        $response = $this->get(route('invoices.index', [
            'tab' => 'overdue',
            'month' => '2025-06',
            'status' => InvoiceStatusEnum::Overdue->value,
        ]));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices')
            ->has('filters'),
        );
    }

    public function test_index_status_options_prop_contains_all_enum_values(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $expectedCount = count(InvoiceStatusEnum::cases());

        // Act
        $response = $this->get(route('invoices.index'));

        // Assert — statusOptions/typeOptions arrays not collapsed during layout refactor
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('statusOptions', $expectedCount)
            ->has('typeOptions', count(InvoiceTypeEnum::cases())),
        );
    }

    // -------------------------------------------------------------------------
    // create — prop shape
    // -------------------------------------------------------------------------

    public function test_create_returns_correct_inertia_component(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act
        $response = $this->get(route('invoices.create'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Invoices/Create'));
    }

    public function test_create_returns_vat_rate_prop(): void
    {
        // Arrange — vatRate is new prop, must not be dropped by layout refactor
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act
        $response = $this->get(route('invoices.create'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Create')
            ->has('vatRate'),
        );
    }

    public function test_create_segmented_type_options_all_present(): void
    {
        // Arrange — segmented control in InvoiceForm.vue needs all typeOptions entries
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $expectedCount = count(InvoiceTypeEnum::cases());

        // Act
        $response = $this->get(route('invoices.create'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Create')
            ->has('typeOptions', $expectedCount),
        );
    }

    // -------------------------------------------------------------------------
    // show — prop shape (used by redesigned Show.vue)
    // -------------------------------------------------------------------------

    public function test_show_returns_correct_inertia_component(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('invoices.show', $invoice));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Invoices/Show'));
    }

    public function test_show_invoice_prop_contains_status_and_number(): void
    {
        // Arrange — InvoiceStatusBadge.vue reads invoice.status; redesigned Show.vue reads invoice.number
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('invoices.show', $invoice));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->has('invoice.status')
            ->has('invoice.number')
            ->where('invoice.id', $invoice->id)
            ->where('invoice.status', InvoiceStatusEnum::Draft->value),
        );
    }

    public function test_show_invoice_prop_contains_financial_fields(): void
    {
        // Arrange — InvoiceTotals composable (extracted from InvoiceItemsEditor) reads subtotal/vat_amount/total
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('invoices.show', $invoice));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->has('invoice.subtotal')
            ->has('invoice.vat_amount')
            ->has('invoice.total')
            ->has('invoice.is_vat_payer'),
        );
    }

    public function test_show_invoice_prop_contains_items_array(): void
    {
        // Arrange — InvoiceItemsEditor consumes items; regression if key renamed/removed
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('invoices.show', $invoice));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->has('invoice.items'),
        );
    }

    public function test_show_invoice_prop_contains_supplier(): void
    {
        // Arrange — supplier block visible in Show.vue layout redesign
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('invoices.show', $invoice));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->has('invoice.supplier'),
        );
    }

    public function test_show_invoice_prop_contains_qr_fields(): void
    {
        // Arrange — Pay-by-Square QR rendered in Show.vue; field must survive layout restructure
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('invoices.show', $invoice));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->has('invoice.qr_available'),
        );
    }

    // -------------------------------------------------------------------------
    // settings/invoicing — prop shape (section nav restructure regression)
    // -------------------------------------------------------------------------

    public function test_settings_invoicing_returns_correct_component(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act
        $response = $this->get(route('settings.invoicing'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Settings/Invoicing'));
    }

    public function test_settings_invoicing_settings_prop_contains_required_keys(): void
    {
        // Arrange — section nav restructure must not strip settings prop keys
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act
        $response = $this->get(route('settings.invoicing'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Invoicing')
            ->has('settings.invoice_template')
            ->has('settings.invoice_number_format')
            ->has('settings.iban')
            ->has('settings.vat_rate')
            ->has('settings.registration_info'),
        );
    }

    public function test_settings_invoicing_templates_prop_contains_all_enum_values(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $expectedCount = count(InvoiceTemplateEnum::cases());

        // Act
        $response = $this->get(route('settings.invoicing'));

        // Assert — template options array used by section nav; must not be empty
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Invoicing')
            ->has('templates', $expectedCount),
        );
    }

    // -------------------------------------------------------------------------
    // cross-cutting: status badge values match enum cases
    // -------------------------------------------------------------------------

    public function test_list_item_status_field_is_valid_enum_value(): void
    {
        // Arrange — InvoiceStatusBadge.vue maps status string; badge class mapping changed in Track A.
        // If status value changes format the badge would render wrong class silently.
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('invoices.index'));

        // Assert — status is the raw enum string value, not a label
        $validValues = array_column(InvoiceStatusEnum::cases(), 'value');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.status', fn ($v) => in_array($v, $validValues, true)),
        );
    }

    public function test_invoices_index_with_client_filter_returns_correct_subset(): void
    {
        // Arrange — client_id filter used in FE; verify backend filter still wired
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        Invoice::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);
        // Standalone invoice (no client)
        Invoice::factory()->count(1)->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('invoices.index', ['filter[client_id]' => $client->id]));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 2),
        );
    }
}
