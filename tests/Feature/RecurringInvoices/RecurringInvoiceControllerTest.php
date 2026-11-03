<?php

declare(strict_types=1);

namespace Tests\Feature\RecurringInvoices;

use App\Data\Invoices\InvoiceListItemData;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\QuoteBillingPeriodEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
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
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('RecurringInvoices/Show')->where('recurringInvoice.id', $ri->id));
    }

    public function test_show_generated_invoices_do_not_produce_n_plus_1_queries(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = $this->makeTemplate($tenant);

        $twoRowsQueryCount = $this->queryCountForGeneratedInvoicesFetch($tenant, $ri, 2);
        $fiveRowsQueryCount = $this->queryCountForGeneratedInvoicesFetch($tenant, $ri, 5);

        // Eager loading (->with('client:id,name')) keeps the query count flat regardless
        // of row count; an N+1 (one lazy client lookup per generated invoice) would grow
        // this by 3 when going from 2 to 5 rows.
        $this->assertSame(
            $twoRowsQueryCount,
            $fiveRowsQueryCount,
            "Query count grew from {$twoRowsQueryCount} (2 rows) to {$fiveRowsQueryCount} (5 rows) — N+1 suspected.",
        );
    }

    private function queryCountForGeneratedInvoicesFetch(Tenant $tenant, RecurringInvoice $ri, int $count): int
    {
        Invoice::where('recurring_invoice_id', $ri->id)->delete();

        for ($i = 0; $i < $count; $i++) {
            $client = Client::factory()->create(['tenant_id' => $tenant->id]);
            Invoice::factory()->create(['tenant_id' => $tenant->id, 'recurring_invoice_id' => $ri->id, 'client_id' => $client->id]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $generatedInvoices = $ri->generatedInvoices()
            ->with('client:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $items = $generatedInvoices->map(fn (Invoice $invoice) => InvoiceListItemData::fromModel($invoice))->all();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount($count, $items);

        return $queryCount;
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

    public function test_store_header_text_over_1000_chars_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->storePayload(['header_text' => str_repeat('a', 1001)]);

        $this->post(route('recurring-invoices.store'), $payload)->assertSessionHasErrors('header_text');
    }

    public function test_store_footer_text_over_1000_chars_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->storePayload(['footer_text' => str_repeat('a', 1001)]);

        $this->post(route('recurring-invoices.store'), $payload)->assertSessionHasErrors('footer_text');
    }

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

    public function test_store_rejects_auto_send_without_auto_issue(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->post(route('recurring-invoices.store'), $this->storePayload(['auto_issue' => false, 'auto_send' => true]))
            ->assertSessionHasErrors('auto_send');

        $this->assertDatabaseCount('recurring_invoices', 0);
    }

    public function test_store_persists_auto_send_with_auto_issue(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->post(route('recurring-invoices.store'), $this->storePayload(['auto_issue' => true, 'auto_send' => true]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('recurring_invoices', ['name' => 'Monthly Test', 'auto_issue' => true, 'auto_send' => true]);
    }

    public function test_update_rejects_auto_send_without_auto_issue_and_leaves_template_unchanged(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $ri = $this->makeTemplate($tenant, ['auto_issue' => true, 'auto_send' => true]);

        $this->put(route('recurring-invoices.update', $ri), $this->storePayload(['auto_issue' => false, 'auto_send' => true, 'name' => 'Changed']))
            ->assertSessionHasErrors('auto_send');

        $ri->refresh();
        $this->assertTrue($ri->auto_issue);
        $this->assertTrue($ri->auto_send);
        $this->assertNotSame('Changed', $ri->name);
    }

    public function test_update_allows_disabling_both_flags(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $ri = $this->makeTemplate($tenant, ['auto_issue' => true, 'auto_send' => true]);

        $this->put(route('recurring-invoices.update', $ri), $this->storePayload(['auto_issue' => false, 'auto_send' => false]))
            ->assertSessionDoesntHaveErrors();

        $this->assertFalse($ri->refresh()->auto_send);
    }

    /**
     * @param  list<QuoteBillingPeriodEnum>  $periods
     * @param  array<string, mixed>  $quoteState
     */
    private function acceptedQuote(Tenant $tenant, array $periods, array $quoteState = []): Quote
    {
        $quote = Quote::factory()->accepted()->create(array_merge(['tenant_id' => $tenant->id], $quoteState));

        foreach ($periods as $position => $period) {
            QuoteItem::factory()->create([
                'tenant_id' => $tenant->id,
                'quote_id' => $quote->id,
                'description' => $period->value.' item',
                'billing_period' => $period,
                'position' => $position,
            ]);
        }

        return $quote;
    }

    public function test_create_without_query_has_null_prefill(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('recurring-invoices.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('prefill', null));
    }

    public function test_create_with_invalid_query_params_has_null_prefill(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('recurring-invoices.create', ['quote_id' => 'nope', 'billing_period' => 'monthly']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('prefill', null));
        $this->get(route('recurring-invoices.create', ['quote_id' => (string) Str::uuid(), 'billing_period' => 'weekly']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('prefill', null));
    }

    public function test_create_prefills_from_accepted_quote_group(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = $this->acceptedQuote($tenant, [QuoteBillingPeriodEnum::OneOff, QuoteBillingPeriodEnum::Monthly, QuoteBillingPeriodEnum::Yearly]);

        $this->get(route('recurring-invoices.create', ['quote_id' => $quote->id, 'billing_period' => 'yearly']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('prefill.quote_id', $quote->id)
                ->where('prefill.frequency', 'annually')
                ->has('prefill.items', 1)
                ->where('prefill.items.0.description', 'yearly item'));
    }

    public function test_create_prefill_redirects_back_with_status_error_when_quote_not_accepted(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('recurring-invoices.create', ['quote_id' => $quote->id, 'billing_period' => 'monthly']))
            ->assertSessionHasErrors('status');
    }

    public function test_create_prefill_rejects_one_off_and_empty_group(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = $this->acceptedQuote($tenant, [QuoteBillingPeriodEnum::OneOff]);

        $this->get(route('recurring-invoices.create', ['quote_id' => $quote->id, 'billing_period' => 'one_off']))
            ->assertSessionHasErrors(['status' => __('app.quote_has_no_items_for_period')]);
        $this->get(route('recurring-invoices.create', ['quote_id' => $quote->id, 'billing_period' => 'monthly']))
            ->assertSessionHasErrors(['status' => __('app.quote_has_no_items_for_period')]);
    }

    public function test_create_prefill_for_foreign_tenant_quote_is_not_found(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $foreign = $this->acceptedQuote(Tenant::factory()->create(), [QuoteBillingPeriodEnum::Monthly]);

        $this->get(route('recurring-invoices.create', ['quote_id' => $foreign->id, 'billing_period' => 'monthly']))
            ->assertNotFound();
    }

    public function test_create_prefill_forbidden_without_create_recurring_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $quote = $this->acceptedQuote($tenant, [QuoteBillingPeriodEnum::Monthly]);

        $this->get(route('recurring-invoices.create', ['quote_id' => $quote->id, 'billing_period' => 'monthly']))
            ->assertForbidden();
    }

    public function test_create_prefill_forbidden_without_view_on_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);
        $role = Role::inTenant($tenant->id)->where('name', 'Účtovníčka')->firstOrFail();
        $role->revokePermissionTo(PermissionEnum::ViewQuotes->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $quote = $this->acceptedQuote($tenant, [QuoteBillingPeriodEnum::Monthly]);

        $this->get(route('recurring-invoices.create', ['quote_id' => $quote->id, 'billing_period' => 'monthly']))
            ->assertForbidden();
    }

    public function test_store_rejects_item_description_over_255_chars(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->post(route('recurring-invoices.store'), $this->storePayload([
            'items' => [['description' => str_repeat('a', 256), 'quantity' => 1, 'unit_price' => 100]],
        ]))->assertSessionHasErrors('items.0.description');
    }
}
