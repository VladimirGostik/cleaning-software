<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Jobs\GenerateRecurringInvoiceJob;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Services\Pdf\PayBySquareService;
use App\Services\RecurringInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Part A calc engine: per-item VAT, discounts, deposit, vat_breakdown recap.
 */
final class InvoiceVatCalcTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Service',
            'quantity' => 1.0,
            'unit_price' => 100.0,
            'discount_percent' => 0.0,
            'vat_rate' => 0.0,
        ], $overrides);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function invoicePayload(array $items = [], array $overrides = []): array
    {
        return array_merge([
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'customer_name' => 'Test Corp s.r.o.',
            'deposit' => 0,
            'items' => $items ?: [$this->itemPayload()],
        ], $overrides);
    }

    // =========================================================================
    // Group 1: VAT payer — per-item calc + recap
    // =========================================================================

    public function test_vat_payer_three_rate_items_compute_correct_line_breakdown_and_recap(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();
        $tenant->update(['is_vat_payer' => true, 'vat_rate' => 23]);

        $service = app(InvoiceService::class);

        // item23: 10 × 100 − 10% = 900.00 base; 900 × 0.23 = 207.00 vat; 1107.00 total
        // item19: 5  × 50         = 250.00 base; 250 × 0.19 =  47.50 vat;  297.50 total
        // item0:  2  × 30         =  60.00 base;   0 × 0.00 =   0.00 vat;   60.00 total
        $data = InvoiceUpsertData::from($this->invoicePayload([
            $this->itemPayload(['description' => 'Item23', 'quantity' => 10.0, 'unit_price' => 100.0, 'discount_percent' => 10.0, 'vat_rate' => 23.0]),
            $this->itemPayload(['description' => 'Item19', 'quantity' => 5.0,  'unit_price' => 50.0, 'discount_percent' => 0.0, 'vat_rate' => 19.0]),
            $this->itemPayload(['description' => 'Item0',  'quantity' => 2.0,  'unit_price' => 30.0, 'discount_percent' => 0.0, 'vat_rate' => 0.0]),
        ]));

        // Act
        $invoice = $service->create($data);
        $invoice->loadMissing('items');

        // Assert per-item line values
        $item23 = $invoice->items->firstWhere('description', 'Item23');
        $item19 = $invoice->items->firstWhere('description', 'Item19');
        $item0 = $invoice->items->firstWhere('description', 'Item0');

        $this->assertNotNull($item23);
        $this->assertEqualsWithDelta(900.00, (float) $item23->line_base, 0.001);
        $this->assertEqualsWithDelta(207.00, (float) $item23->line_vat, 0.001);
        $this->assertEqualsWithDelta(1107.00, (float) $item23->line_total, 0.001);

        $this->assertNotNull($item19);
        $this->assertEqualsWithDelta(250.00, (float) $item19->line_base, 0.001);
        $this->assertEqualsWithDelta(47.50, (float) $item19->line_vat, 0.001);
        $this->assertEqualsWithDelta(297.50, (float) $item19->line_total, 0.001);

        $this->assertNotNull($item0);
        $this->assertEqualsWithDelta(60.00, (float) $item0->line_base, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $item0->line_vat, 0.001);
        $this->assertEqualsWithDelta(60.00, (float) $item0->line_total, 0.001);

        // Assert invoice-level aggregates
        $this->assertEqualsWithDelta(1210.00, (float) $invoice->subtotal, 0.001); // 900+250+60
        $this->assertEqualsWithDelta(254.50, (float) $invoice->vat_amount, 0.001); // 207+47.5
        $this->assertEqualsWithDelta(1464.50, (float) $invoice->total, 0.001); // 1210+254.5

        // Assert vat_breakdown — 3 entries sorted desc by rate
        $this->assertIsArray($invoice->vat_breakdown);
        $this->assertCount(3, $invoice->vat_breakdown);

        $this->assertEqualsWithDelta(23.0, $invoice->vat_breakdown[0]['rate'], 0.001);
        $this->assertEqualsWithDelta(900.00, $invoice->vat_breakdown[0]['base'], 0.001);
        $this->assertEqualsWithDelta(207.00, $invoice->vat_breakdown[0]['vat'], 0.001);
        $this->assertEqualsWithDelta(1107.00, $invoice->vat_breakdown[0]['total'], 0.001);

        $this->assertEqualsWithDelta(19.0, $invoice->vat_breakdown[1]['rate'], 0.001);
        $this->assertEqualsWithDelta(250.00, $invoice->vat_breakdown[1]['base'], 0.001);
        $this->assertEqualsWithDelta(47.50, $invoice->vat_breakdown[1]['vat'], 0.001);

        $this->assertEqualsWithDelta(0.0, $invoice->vat_breakdown[2]['rate'], 0.001);
        $this->assertEqualsWithDelta(60.0, $invoice->vat_breakdown[2]['base'], 0.001);
    }

    public function test_deposit_persisted_and_balance_due_equals_total_minus_deposit(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();
        $tenant->update(['is_vat_payer' => false]);

        $service = app(InvoiceService::class);

        // qty=1, price=500, no VAT → total=500; deposit=50 → balance_due=450
        $data = InvoiceUpsertData::from($this->invoicePayload(
            [$this->itemPayload(['quantity' => 1.0, 'unit_price' => 500.0])],
            ['deposit' => 50.0],
        ));

        // Act
        $invoice = $service->create($data);

        // Assert
        $this->assertEqualsWithDelta(500.00, (float) $invoice->total, 0.001);
        $this->assertEqualsWithDelta(50.00, (float) $invoice->deposit, 0.001);
        $this->assertEqualsWithDelta(450.00, $invoice->balance_due, 0.001);
    }

    public function test_discount_100_percent_yields_zero_line_base_and_vat(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();
        $tenant->update(['is_vat_payer' => true]);

        $service = app(InvoiceService::class);

        $data = InvoiceUpsertData::from($this->invoicePayload([
            $this->itemPayload(['quantity' => 5.0, 'unit_price' => 100.0, 'discount_percent' => 100.0, 'vat_rate' => 23.0]),
        ]));

        // Act
        $invoice = $service->create($data);
        $invoice->loadMissing('items');

        // Assert: 5 × 100 × (1 − 1.0) = 0; 0 × 0.23 = 0
        $item = $invoice->items->first();
        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(0.00, (float) $item->line_base, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $item->line_vat, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $item->line_total, 0.001);

        $this->assertEqualsWithDelta(0.00, (float) $invoice->subtotal, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $invoice->vat_amount, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $invoice->total, 0.001);
    }

    // =========================================================================
    // Group 2: Validation — discount_percent > 100 rejected
    // =========================================================================

    public function test_discount_over_100_fails_validation_via_http(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');

        $payload = $this->invoicePayload([
            $this->itemPayload(['discount_percent' => 110.0]),
        ]);

        // Act
        $response = $this->post(route('invoices.store'), $payload);

        // Assert: Spatie Data Between(0,100) triggers a validation error
        $response->assertSessionHasErrors();
    }

    // =========================================================================
    // Group 3: Non-VAT payer — all vat_rate inputs ignored, breakdown null
    // =========================================================================

    public function test_non_vat_payer_all_item_vat_zeroed_and_breakdown_null(): void
    {
        // Arrange: tenant is NOT a VAT payer
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();
        $tenant->update(['is_vat_payer' => false]);

        $service = app(InvoiceService::class);

        // Pass vat_rate=23 — service must force it to 0 for non-payer
        $data = InvoiceUpsertData::from($this->invoicePayload([
            $this->itemPayload(['quantity' => 2.0, 'unit_price' => 100.0, 'vat_rate' => 23.0]),
        ]));

        // Act
        $invoice = $service->create($data);
        $invoice->loadMissing('items');

        // Assert: per-item vat forced to 0
        $item = $invoice->items->first();
        $this->assertNotNull($item);
        $this->assertEqualsWithDelta(200.00, (float) $item->line_base, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $item->line_vat, 0.001);
        $this->assertEqualsWithDelta(200.00, (float) $item->line_total, 0.001);

        // Assert: invoice-level vat = 0, breakdown = null ([] ?: null)
        $this->assertEqualsWithDelta(0.00, (float) $invoice->vat_amount, 0.001);
        $this->assertNull($invoice->vat_breakdown);
    }

    // =========================================================================
    // Group 4: Cancel — credit note negates line columns, deposit, breakdown
    // =========================================================================

    public function test_cancel_credit_note_negates_line_columns_deposit_and_breakdown(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'is_vat_payer' => true,
            'subtotal' => '100.00',
            'vat_amount' => '23.00',
            'total' => '123.00',
            'deposit' => '20.00',
            'vat_breakdown' => [['rate' => 23.0, 'base' => 100.0, 'vat' => 23.0, 'total' => 123.0]],
        ]);

        InvoiceItem::factory()->create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'description' => 'Test service',
            'quantity' => 1.0,
            'unit_price' => 100.0,
            'discount_percent' => 0.0,
            'vat_rate' => 23.0,
            'line_base' => 100.0,
            'line_vat' => 23.0,
            'line_total' => 123.0,
            'position' => 0,
        ]);

        // Act
        $response = $this->post(route('invoices.cancel', $invoice));
        $response->assertRedirect();

        $creditNote = Invoice::withoutGlobalScopes()
            ->where('credited_invoice_id', $invoice->id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        // Assert: negated invoice-level aggregates
        $this->assertEqualsWithDelta(-123.00, (float) $creditNote->total, 0.001);
        $this->assertEqualsWithDelta(-20.00, (float) $creditNote->deposit, 0.001);

        // Assert: negated vat_breakdown entries
        $this->assertIsArray($creditNote->vat_breakdown);
        $this->assertCount(1, $creditNote->vat_breakdown);
        $this->assertEqualsWithDelta(23.0, $creditNote->vat_breakdown[0]['rate'], 0.001);
        $this->assertEqualsWithDelta(-100.00, $creditNote->vat_breakdown[0]['base'], 0.001);
        $this->assertEqualsWithDelta(-23.00, $creditNote->vat_breakdown[0]['vat'], 0.001);
        $this->assertEqualsWithDelta(-123.00, $creditNote->vat_breakdown[0]['total'], 0.001);

        // Assert: credit note item line columns negated
        $creditNote->loadMissing('items');
        $creditItem = $creditNote->items->first();
        $this->assertNotNull($creditItem);
        $this->assertEqualsWithDelta(-100.00, (float) $creditItem->line_base, 0.001);
        $this->assertEqualsWithDelta(-23.00, (float) $creditItem->line_vat, 0.001);
        $this->assertEqualsWithDelta(-123.00, (float) $creditItem->line_total, 0.001);
        $this->assertEqualsWithDelta(23.0, (float) $creditItem->vat_rate, 0.001); // rate preserved
    }

    // =========================================================================
    // Group 5: Duplicate — copies all new columns as-is, status Draft
    // =========================================================================

    public function test_duplicate_copies_vat_columns_and_deposit_as_draft(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();

        $original = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'is_vat_payer' => true,
            'subtotal' => '250.00',
            'vat_amount' => '47.50',
            'total' => '297.50',
            'deposit' => '30.00',
            'vat_breakdown' => [['rate' => 19.0, 'base' => 250.0, 'vat' => 47.5, 'total' => 297.5]],
        ]);

        InvoiceItem::factory()->create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $original->id,
            'description' => 'Original item',
            'quantity' => 5.0,
            'unit_price' => 50.0,
            'discount_percent' => 0.0,
            'vat_rate' => 19.0,
            'line_base' => 250.0,
            'line_vat' => 47.5,
            'line_total' => 297.5,
            'position' => 0,
        ]);

        // Act
        $response = $this->post(route('invoices.duplicate', $original));
        $response->assertRedirect();

        $duplicate = Invoice::where('tenant_id', $tenant->id)
            ->where('id', '!=', $original->id)
            ->where('status', InvoiceStatusEnum::Draft->value)
            ->firstOrFail();

        // Assert: invoice-level columns copied
        $this->assertSame(InvoiceStatusEnum::Draft, $duplicate->status);
        $this->assertEqualsWithDelta(297.50, (float) $duplicate->total, 0.001);
        $this->assertEqualsWithDelta(30.00, (float) $duplicate->deposit, 0.001);
        $this->assertEqualsWithDelta(267.50, $duplicate->balance_due, 0.001); // 297.5 − 30

        // Assert: vat_breakdown copied
        $this->assertIsArray($duplicate->vat_breakdown);
        $this->assertCount(1, $duplicate->vat_breakdown);
        $this->assertEqualsWithDelta(19.0, $duplicate->vat_breakdown[0]['rate'], 0.001);

        // Assert: item columns copied
        $duplicate->loadMissing('items');
        $dupItem = $duplicate->items->first();
        $this->assertNotNull($dupItem);
        $this->assertEqualsWithDelta(19.0, (float) $dupItem->vat_rate, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $dupItem->discount_percent, 0.001);
        $this->assertEqualsWithDelta(250.0, (float) $dupItem->line_base, 0.001);
        $this->assertEqualsWithDelta(47.5, (float) $dupItem->line_vat, 0.001);
        $this->assertEqualsWithDelta(297.5, (float) $dupItem->line_total, 0.001);
    }

    // =========================================================================
    // Group 6: RecurringInvoiceService::generateInvoiceFromTemplate
    // =========================================================================

    public function test_generate_from_template_passes_vat_rate_discount_and_deposit(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();
        $tenant->update(['is_vat_payer' => true]);
        $tenantId = $tenant->id;

        $ri = RecurringInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'type' => InvoiceTypeEnum::Monthly,
            'frequency' => RecurringFrequencyEnum::Monthly,
            'day_of_month' => 15,
            'status' => RecurringInvoiceStatusEnum::Active,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'next_run_at' => now()->toDateString(),
            'customer_name' => 'Recurring Corp',
            'due_days' => 14,
            'deposit' => '50.00',
        ]);

        RecurringInvoiceItem::factory()->create([
            'tenant_id' => $tenantId,
            'recurring_invoice_id' => $ri->id,
            'description' => 'Monthly service',
            'quantity' => 4.0,
            'unit_price' => 100.0,
            'discount_percent' => 10.0,
            'vat_rate' => 23.0,
            'position' => 0,
        ]);

        // Act
        $service = app(RecurringInvoiceService::class);
        $invoice = $service->generateInvoiceFromTemplate($ri);
        $invoice->loadMissing('items');

        // Assert: item passes through vat_rate and discount_percent from template
        $invoiceItem = $invoice->items->first();
        $this->assertNotNull($invoiceItem);
        $this->assertEqualsWithDelta(23.0, (float) $invoiceItem->vat_rate, 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $invoiceItem->discount_percent, 0.001);

        // 4 × 100 × (1 − 0.1) = 360.00 base; 360 × 0.23 = 82.80 vat; 442.80 total
        $this->assertEqualsWithDelta(360.00, (float) $invoiceItem->line_base, 0.001);
        $this->assertEqualsWithDelta(82.80, (float) $invoiceItem->line_vat, 0.001);
        $this->assertEqualsWithDelta(442.80, (float) $invoiceItem->line_total, 0.001);

        // Assert: deposit transferred from recurring template
        $this->assertEqualsWithDelta(50.00, (float) $invoice->deposit, 0.001);

        // Assert: vat_breakdown populated
        $this->assertIsArray($invoice->vat_breakdown);
        $this->assertCount(1, $invoice->vat_breakdown);
        $this->assertEqualsWithDelta(23.0, $invoice->vat_breakdown[0]['rate'], 0.001);
        $this->assertEqualsWithDelta(360.00, $invoice->vat_breakdown[0]['base'], 0.001);
        $this->assertEqualsWithDelta(82.80, $invoice->vat_breakdown[0]['vat'], 0.001);
    }

    // =========================================================================
    // Group 7: GenerateRecurringInvoiceJob — end-to-end breakdown persistence
    // =========================================================================

    public function test_job_generates_invoice_with_vat_breakdown_from_template(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();
        $tenant->update(['is_vat_payer' => true]);
        $tenantId = $tenant->id;

        $ri = RecurringInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'type' => InvoiceTypeEnum::Monthly,
            'frequency' => RecurringFrequencyEnum::Monthly,
            'day_of_month' => 15,
            'status' => RecurringInvoiceStatusEnum::Active,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'next_run_at' => now()->toDateString(),
            'customer_name' => 'Job Corp',
            'due_days' => 14,
        ]);

        RecurringInvoiceItem::factory()->create([
            'tenant_id' => $tenantId,
            'recurring_invoice_id' => $ri->id,
            'description' => 'Service item',
            'quantity' => 1.0,
            'unit_price' => 200.0,
            'discount_percent' => 0.0,
            'vat_rate' => 23.0,
            'position' => 0,
        ]);

        // Act
        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        // Assert
        $invoice = Invoice::where('recurring_invoice_id', $ri->id)->firstOrFail();

        $this->assertIsArray($invoice->vat_breakdown);
        $this->assertNotEmpty($invoice->vat_breakdown);
        $this->assertEqualsWithDelta(23.0, $invoice->vat_breakdown[0]['rate'], 0.001);
        $this->assertEqualsWithDelta(200.00, $invoice->vat_breakdown[0]['base'], 0.001);
        $this->assertEqualsWithDelta(46.00, $invoice->vat_breakdown[0]['vat'], 0.001);

        // 1 × 200 = 200 subtotal; 200 × 0.23 = 46 vat; 246 total
        $this->assertEqualsWithDelta(200.00, (float) $invoice->subtotal, 0.001);
        $this->assertEqualsWithDelta(46.00, (float) $invoice->vat_amount, 0.001);
        $this->assertEqualsWithDelta(246.00, (float) $invoice->total, 0.001);
    }

    // =========================================================================
    // Group 8: PayBySquareService — uses balance_due, not total
    // =========================================================================

    public function test_pay_by_square_returns_null_when_balance_due_is_zero(): void
    {
        // Arrange: deposit = total → balance_due = 0 → QR must be null
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'total' => '100.00',
            'deposit' => '100.00',
            'supplier_iban' => 'SK3112000000198742637541',
        ]);

        // Act
        $result = (new PayBySquareService)->dataUri($invoice);

        // Assert
        $this->assertNull($result);
    }

    public function test_pay_by_square_returns_null_when_balance_due_is_negative(): void
    {
        // Arrange: deposit > total → balance_due < 0 (overpayment/credit)
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'total' => '100.00',
            'deposit' => '150.00',
            'supplier_iban' => 'SK3112000000198742637541',
        ]);

        // Act
        $result = (new PayBySquareService)->dataUri($invoice);

        // Assert
        $this->assertNull($result);
    }

    public function test_pay_by_square_returns_non_null_for_positive_balance_due(): void
    {
        // Arrange: total=200, deposit=50 → balance_due=150 → QR encoded with 150
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'total' => '200.00',
            'deposit' => '50.00',
            'supplier_iban' => 'SK3112000000198742637541',
        ]);

        // Act
        $result = (new PayBySquareService)->dataUri($invoice);

        // Non-null confirms service used balance_due (150 > 0) rather than being blocked.
        // The underlying QR library encodes the exact amount; the guard is what we can unit-test.
        $this->assertNotNull($result);
    }

    // =========================================================================
    // Group 9: PDF Blade partial — totals renders recap + deposit + balance_due
    // =========================================================================

    public function test_totals_partial_renders_vat_recap_and_deposit_row(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'is_vat_payer' => true,
            'subtotal' => '100.00',
            'vat_amount' => '23.00',
            'total' => '123.00',
            'deposit' => '10.00',
            'vat_breakdown' => [
                ['rate' => 23.0, 'base' => 100.0, 'vat' => 23.0, 'total' => 123.0],
            ],
        ]);

        // Act: render the partial directly (no PDF driver involved)
        $html = view('pdf.invoices.partials.totals', ['invoice' => $invoice])->render();

        // Assert: VAT rate "23 %" appears in recap table
        $this->assertStringContainsString('23', $html);

        // Assert: deposit row rendered with SK decimal notation (10,00 €)
        $this->assertStringContainsString('10,00', $html);

        // Assert: balance_due row rendered — 123 − 10 = 113.00
        $this->assertStringContainsString('113,00', $html);
    }
}
