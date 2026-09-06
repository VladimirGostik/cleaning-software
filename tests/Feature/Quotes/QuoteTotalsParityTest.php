<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Data\Quotes\QuoteItemData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\InvoiceTypeEnum;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuoteTotalsParityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{quantity: float, unit_price: float, discount_percent: float, vat_rate: float}> */
    private function items(): array
    {
        return [
            ['quantity' => 3, 'unit_price' => 40, 'discount_percent' => 10, 'vat_rate' => 23],
            ['quantity' => 1, 'unit_price' => 15, 'discount_percent' => 0, 'vat_rate' => 5],
        ];
    }

    public function test_quote_and_invoice_totals_match_for_vat_payer_tenant(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => true, 'vat_rate' => 23]);
        $this->bindTenant($tenant);

        $invoiceItems = array_map(fn (array $i) => InvoiceItemData::from([
            'id' => null, 'description' => 'Item', 'quantity' => $i['quantity'], 'unit' => null,
            'unit_price' => $i['unit_price'], 'discount_percent' => $i['discount_percent'], 'vat_rate' => $i['vat_rate'],
        ]), $this->items());

        $invoice = app(InvoiceService::class)->create(InvoiceUpsertData::from([
            'client_id' => null, 'cleaning_object_id' => null, 'type' => InvoiceTypeEnum::OneOff->value,
            'template' => null, 'issue_date' => now()->toDateString(), 'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(), 'period_from' => null, 'period_to' => null,
            'customer_name' => 'Parity Co', 'customer_representative' => null, 'customer_ico' => null,
            'customer_dic' => null, 'customer_vat_number' => null, 'customer_street' => null, 'customer_city' => null,
            'customer_postal_code' => null, 'customer_country' => null, 'customer_email' => null, 'note' => null,
            'items' => $invoiceItems, 'constant_symbol' => null, 'specific_symbol' => null, 'header_text' => null,
            'footer_text' => null, 'deposit' => 0, 'payment_type' => 'transfer', 'currency' => 'EUR', 'rounding_mode' => 'none',
        ]));

        $quoteItems = array_map(fn (array $i) => new QuoteItemData(
            id: null, description: 'Item', frequency: null, quantity: $i['quantity'], unit: null,
            unit_price: $i['unit_price'], discount_percent: $i['discount_percent'], vat_rate: $i['vat_rate'],
        ), $this->items());

        $quote = app(QuoteService::class)->create(QuoteUpsertData::from([
            'client_id' => null, 'cleaning_object_id' => null, 'subject' => null,
            'issue_date' => now()->toDateString(), 'valid_until' => now()->addDays(30)->toDateString(), 'note' => null,
            'items' => $quoteItems, 'customer_name' => 'Parity Co', 'customer_email' => null, 'customer_street' => null,
            'customer_city' => null, 'customer_postal_code' => null, 'number' => null, 'document_uuid' => null,
            'kind' => 'itemized', 'currency' => 'EUR',
        ]), null, 'sess-1');

        $this->assertSame($invoice->subtotal, $quote->subtotal);
        $this->assertSame($invoice->vat_amount, $quote->vat_amount);
        $this->assertSame($invoice->total, $quote->total);
    }

    public function test_quote_and_invoice_totals_match_for_non_vat_payer_tenant(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false, 'vat_rate' => 0]);
        $this->bindTenant($tenant);

        $invoiceItems = array_map(fn (array $i) => InvoiceItemData::from([
            'id' => null, 'description' => 'Item', 'quantity' => $i['quantity'], 'unit' => null,
            'unit_price' => $i['unit_price'], 'discount_percent' => $i['discount_percent'], 'vat_rate' => $i['vat_rate'],
        ]), $this->items());

        $invoice = app(InvoiceService::class)->create(InvoiceUpsertData::from([
            'client_id' => null, 'cleaning_object_id' => null, 'type' => InvoiceTypeEnum::OneOff->value,
            'template' => null, 'issue_date' => now()->toDateString(), 'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(), 'period_from' => null, 'period_to' => null,
            'customer_name' => 'Parity Co', 'customer_representative' => null, 'customer_ico' => null,
            'customer_dic' => null, 'customer_vat_number' => null, 'customer_street' => null, 'customer_city' => null,
            'customer_postal_code' => null, 'customer_country' => null, 'customer_email' => null, 'note' => null,
            'items' => $invoiceItems, 'constant_symbol' => null, 'specific_symbol' => null, 'header_text' => null,
            'footer_text' => null, 'deposit' => 0, 'payment_type' => 'transfer', 'currency' => 'EUR', 'rounding_mode' => 'none',
        ]));

        $quoteItems = array_map(fn (array $i) => new QuoteItemData(
            id: null, description: 'Item', frequency: null, quantity: $i['quantity'], unit: null,
            unit_price: $i['unit_price'], discount_percent: $i['discount_percent'], vat_rate: $i['vat_rate'],
        ), $this->items());

        $quote = app(QuoteService::class)->create(QuoteUpsertData::from([
            'client_id' => null, 'cleaning_object_id' => null, 'subject' => null,
            'issue_date' => now()->toDateString(), 'valid_until' => now()->addDays(30)->toDateString(), 'note' => null,
            'items' => $quoteItems, 'customer_name' => 'Parity Co', 'customer_email' => null, 'customer_street' => null,
            'customer_city' => null, 'customer_postal_code' => null, 'number' => null, 'document_uuid' => null,
            'kind' => 'itemized', 'currency' => 'EUR',
        ]), null, 'sess-1');

        $this->assertSame($invoice->subtotal, $quote->subtotal);
        $this->assertSame('0.00', $quote->vat_amount);
        $this->assertSame($invoice->total, $quote->total);
        $this->assertNull($quote->vat_breakdown);
    }
}
