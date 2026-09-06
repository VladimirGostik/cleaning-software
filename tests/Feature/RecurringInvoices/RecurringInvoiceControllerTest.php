<?php

declare(strict_types=1);

namespace Tests\Feature\RecurringInvoices;

use App\Enums\InvoiceTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class RecurringInvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $overrides */
    private function makeTemplate(Tenant $tenant, array $overrides = []): RecurringInvoice
    {
        $ri = RecurringInvoice::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'type' => InvoiceTypeEnum::Monthly,
            'frequency' => RecurringFrequencyEnum::Monthly,
            'day_of_month' => 15,
            'status' => RecurringInvoiceStatusEnum::Active,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'next_run_at' => now()->addMonth()->toDateString(),
            'customer_name' => 'Test Customer',
            'due_days' => 14,
        ], $overrides));

        RecurringInvoiceItem::factory()->create(['tenant_id' => $tenant->id, 'recurring_invoice_id' => $ri->id]);

        return $ri;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Monthly Test',
            'type' => InvoiceTypeEnum::Monthly->value,
            'frequency' => RecurringFrequencyEnum::Monthly->value,
            'day_of_month' => 15,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'due_days' => 14,
            'customer_name' => 'Acme s.r.o.',
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'items' => [
                ['description' => 'Cleaning', 'quantity' => 1, 'unit_price' => 100],
            ],
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_index_returns_200_for_authorized_user(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('recurring-invoices.index'))->assertOk();
    }

    public function test_create_returns_200_for_authorized_user(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('recurring-invoices.create'))->assertOk();
    }

    public function test_store_creates_recurring_invoice_and_redirects(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->post(route('recurring-invoices.store'), $this->storePayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('recurring_invoices', ['name' => 'Monthly Test']);
    }

    public function test_show_returns_200_for_authorized_user(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $ri = $this->makeTemplate($tenant);

        $response = $this->get(route('recurring-invoices.show', $ri));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('RecurringInvoices/Show', shouldExist: false)->where('recurringInvoice.id', $ri->id));
    }

    public function test_pause_sets_status_to_paused(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $ri = $this->makeTemplate($tenant);

        $this->post(route('recurring-invoices.pause', $ri))->assertRedirect();

        $ri->refresh();
        $this->assertSame(RecurringInvoiceStatusEnum::Paused, $ri->status);
    }

    public function test_resume_sets_status_to_active(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $ri = $this->makeTemplate($tenant);
        $ri->update(['status' => RecurringInvoiceStatusEnum::Paused, 'next_run_at' => null]);

        $this->post(route('recurring-invoices.resume', $ri))->assertRedirect();

        $ri->refresh();
        $this->assertSame(RecurringInvoiceStatusEnum::Active, $ri->status);
    }

    public function test_cancel_sets_status_to_cancelled(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $ri = $this->makeTemplate($tenant);

        $this->post(route('recurring-invoices.cancel', $ri))->assertRedirect();

        $ri->refresh();
        $this->assertSame(RecurringInvoiceStatusEnum::Cancelled, $ri->status);
    }

    public function test_destroy_soft_deletes(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $ri = $this->makeTemplate($tenant);

        $this->delete(route('recurring-invoices.destroy', $ri))->assertRedirect();

        $this->assertSoftDeleted('recurring_invoices', ['id' => $ri->id]);
    }

    // -------------------------------------------------------------------------
    // failure
    // -------------------------------------------------------------------------

    public function test_upratovacka_cannot_view_index(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->get(route('recurring-invoices.index'))->assertForbidden();
    }

    public function test_upratovacka_cannot_create(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->post(route('recurring-invoices.store'), $this->storePayload())->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // edge — tenant isolation
    // -------------------------------------------------------------------------

    public function test_cannot_view_other_tenant_recurring_invoice(): void
    {
        $tenantA = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);
        $ri = $this->makeTemplate($tenantA);

        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantB);

        $this->get(route('recurring-invoices.show', $ri))->assertNotFound();
    }
}
