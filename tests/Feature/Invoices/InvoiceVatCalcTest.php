<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceVatCalcTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<int, InvoiceItemData>  $items */
    private function create(Tenant $tenant, array $items, string $roundingMode = 'none'): Invoice
    {
        $data = InvoiceUpsertData::from([
            'client_id' => null,
            'cleaning_object_id' => null,
            'type' => InvoiceTypeEnum::OneOff->value,
            'template' => null,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'period_from' => null,
            'period_to' => null,
            'customer_name' => 'VAT Test Customer',
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
            'items' => $items,
            'constant_symbol' => null,
            'specific_symbol' => null,
            'header_text' => null,
            'footer_text' => null,
            'deposit' => 0,
            'payment_type' => 'transfer',
            'currency' => 'EUR',
            'rounding_mode' => $roundingMode,
        ]);

        return app(InvoiceService::class)->create($data);
    }

    public function test_multiple_vat_rates_grouped_desc_in_breakdown(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => true, 'vat_rate' => 23]);
        $this->bindTenant($tenant);

        $items = [
            new InvoiceItemData(null, 'Item A', 2, 'hod', 30, 10, 23),
            new InvoiceItemData(null, 'Item B', 1, 'ks', 40, 0, 19),
        ];

        $invoice = $this->create($tenant, $items);

        $this->assertSame('94.00', $invoice->subtotal);
        $this->assertNotNull($invoice->vat_breakdown);
        $this->assertCount(2, $invoice->vat_breakdown);
        $this->assertSame(23, $invoice->vat_breakdown[0]['rate']);
        $this->assertSame(19, $invoice->vat_breakdown[1]['rate']);
    }

    public function test_rounding_document_rounds_to_whole_currency(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false, 'vat_rate' => 0]);
        $this->bindTenant($tenant);

        $items = [new InvoiceItemData(null, 'Item', 1, null, 10.4, 0, 0)];
        $invoice = $this->create($tenant, $items, 'document');

        $this->assertSame('10.00', $invoice->total);
        $this->assertSame('-0.40', $invoice->rounding_amount);
    }

    public function test_rounding_cash005_rounds_to_5_cent_step(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false, 'vat_rate' => 0]);
        $this->bindTenant($tenant);

        $items = [new InvoiceItemData(null, 'Item', 1, null, 10.02, 0, 0)];
        $invoice = $this->create($tenant, $items, 'cash_005');

        $this->assertSame('10.00', $invoice->total);
    }

    public function test_rounding_none_produces_zero_rounding_amount(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false, 'vat_rate' => 0]);
        $this->bindTenant($tenant);

        $items = [new InvoiceItemData(null, 'Item', 1, null, 10.4, 0, 0)];
        $invoice = $this->create($tenant, $items, 'none');

        $this->assertSame('0.00', $invoice->rounding_amount);
        $this->assertSame('10.40', $invoice->total);
    }
}
