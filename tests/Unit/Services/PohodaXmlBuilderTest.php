<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\CurrencyEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Services\Export\PohodaXmlBuilder;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LibXMLError;
use Tests\TestCase;

final class PohodaXmlBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['ico' => '12 345 678']);
        $this->bindTenant($this->tenant);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    private function invoice(string $state, array $attributes = [], array $items = []): Invoice
    {
        $factory = match ($state) {
            'issued' => Invoice::factory()->issued(),
            'cancelled' => Invoice::factory()->cancelled(),
            default => Invoice::factory()->issued()->vatPayer(),
        };
        $invoice = $factory->create($attributes + [
            'tenant_id' => $this->tenant->id,
            'issue_date' => '2026-09-10',
            'delivery_date' => '2026-09-09',
            'due_date' => '2026-09-24',
        ]);

        foreach ($items as $position => $item) {
            InvoiceItem::factory()->create($item + ['tenant_id' => $this->tenant->id, 'invoice_id' => $invoice->id, 'position' => $position]);
        }

        return $invoice->load(['items', 'creditedInvoice']);
    }

    /** @return array{0: DOMXPath, 1: string} */
    private function parse(string $xml): array
    {
        $doc = new DOMDocument;
        $this->assertTrue($doc->loadXML($xml), 'XML is not well-formed');
        $this->assertSchemaValid($doc);

        $xp = new DOMXPath($doc);
        $xp->registerNamespace('dat', 'http://www.stormware.cz/schema/version_2/data.xsd');
        $xp->registerNamespace('inv', 'http://www.stormware.cz/schema/version_2/invoice.xsd');
        $xp->registerNamespace('typ', 'http://www.stormware.cz/schema/version_2/type.xsd');

        return [$xp, $xml];
    }

    private function value(DOMXPath $xp, string $query): ?string
    {
        $nodes = $xp->query($query);

        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMNode ? $node->textContent : null;
    }

    private function assertSchemaValid(DOMDocument $doc): void
    {
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valid = $doc->schemaValidate(base_path('tests/Fixtures/pohoda/xsd/data.xsd'));
        $messages = array_map(fn (LibXMLError $e): string => 'line '.$e->line.': '.trim($e->message), libxml_get_errors());
        libxml_clear_errors();

        $this->assertTrue($valid, "Pohoda XSD validation failed:\n".implode("\n", $messages));
    }

    private function nodeCount(DOMXPath $xp, string $query): int
    {
        $nodes = $xp->query($query);

        return $nodes === false ? 0 : $nodes->length;
    }

    /** @return list<string> */
    private function texts(DOMXPath $xp, string $query): array
    {
        $nodes = $xp->query($query);
        $out = [];

        foreach ($nodes === false ? [] : $nodes as $node) {
            if ($node instanceof DOMNode) {
                $out[] = $node->textContent;
            }
        }

        return $out;
    }

    /** @param list<Invoice> $invoices */
    private function build(array $invoices): DOMXPath
    {
        $xml = app(PohodaXmlBuilder::class)->build($this->tenant, new Collection($invoices), '2026-09-01', '2026-09-30');

        return $this->parse($xml)[0];
    }

    public function test_non_vat_invoice_maps_header_partner_items_and_summary(): void
    {
        $invoice = $this->invoice('issued', [
            'number' => 'FA-2026-0001',
            'variable_symbol' => '20260001',
            'constant_symbol' => '0308',
            'customer_name' => 'Acme & Sons <s.r.o.>',
            'customer_ico' => '11112222',
            'customer_dic' => '2020202020',
            'customer_vat_number' => 'SK2020202020',
            'customer_street' => 'Hlavná 1',
            'customer_city' => 'Bratislava',
            'customer_postal_code' => '81101',
            'customer_country' => 'SK',
            'payment_type' => PaymentTypeEnum::Transfer,
        ], [
            ['description' => 'Upratovanie <A&B>', 'quantity' => 2, 'unit' => 'hod', 'unit_price' => 50, 'discount_percent' => 10, 'vat_rate' => 0, 'line_base' => 90, 'line_vat' => 0, 'line_total' => 90],
        ]);

        $xp = $this->build([$invoice]);

        $this->assertSame('12345678', $this->value($xp, '/dat:dataPack/@ico'));
        $this->assertSame('cleanmaster-12345678-2026-09-01-2026-09-30', $this->value($xp, '/dat:dataPack/@id'));
        $this->assertSame('2.0', $this->value($xp, '/dat:dataPack/@version'));
        $this->assertSame('CleanMaster', $this->value($xp, '/dat:dataPack/@application'));
        $this->assertSame('FA-2026-0001', $this->value($xp, '//dat:dataPackItem/@id'));

        $h = '//inv:invoiceHeader/';
        $this->assertSame('issuedInvoice', $this->value($xp, $h.'inv:invoiceType'));
        $this->assertSame('FA-2026-0001', $this->value($xp, $h.'inv:number/typ:numberRequested'));
        $this->assertSame('20260001', $this->value($xp, $h.'inv:symVar'));
        $this->assertSame('0308', $this->value($xp, $h.'inv:symConst'));
        $this->assertSame('2026-09-10', $this->value($xp, $h.'inv:date'));
        $this->assertSame('2026-09-09', $this->value($xp, $h.'inv:dateTax'));
        $this->assertSame('2026-09-10', $this->value($xp, $h.'inv:dateAccounting'));
        $this->assertSame('2026-09-24', $this->value($xp, $h.'inv:dateDue'));
        $this->assertSame('draft', $this->value($xp, $h.'inv:paymentType/typ:paymentType'));
        $this->assertNull($this->value($xp, $h.'inv:note'));

        $a = $h.'inv:partnerIdentity/typ:address/';
        $this->assertSame('Acme & Sons <s.r.o.>', $this->value($xp, $a.'typ:company'));
        $this->assertSame('11112222', $this->value($xp, $a.'typ:ico'));
        $this->assertSame('2020202020', $this->value($xp, $a.'typ:dic'));
        $this->assertSame('SK2020202020', $this->value($xp, $a.'typ:icDph'));
        $this->assertSame('Hlavná 1', $this->value($xp, $a.'typ:street'));
        $this->assertSame('81101', $this->value($xp, $a.'typ:zip'));
        $this->assertSame('SK', $this->value($xp, $a.'typ:country/typ:ids'));

        $i = '//inv:invoiceDetail/inv:invoiceItem[1]/';
        $this->assertSame('Upratovanie <A&B>', $this->value($xp, $i.'inv:text'));
        $this->assertSame('2.00', $this->value($xp, $i.'inv:quantity'));
        $this->assertSame('hod', $this->value($xp, $i.'inv:unit'));
        $this->assertSame('false', $this->value($xp, $i.'inv:payVAT'));
        $this->assertSame('none', $this->value($xp, $i.'inv:rateVAT'));
        $this->assertSame('10.00', $this->value($xp, $i.'inv:discountPercentage'));
        $this->assertSame('50.00', $this->value($xp, $i.'inv:homeCurrency/typ:unitPrice'));
        $this->assertSame('90.00', $this->value($xp, $i.'inv:homeCurrency/typ:price'));
        $this->assertSame('90.00', $this->value($xp, $i.'inv:homeCurrency/typ:priceSum'));

        $s = '//inv:invoiceSummary/';
        $this->assertSame('none', $this->value($xp, $s.'inv:roundingDocument'));
        $this->assertSame('90.00', $this->value($xp, $s.'inv:homeCurrency/typ:priceNone'));
        $this->assertSame(0, $this->nodeCount($xp, '//typ:priceLow | //typ:priceHigh | //typ:price3'));
        $this->assertSame('Faktúra FA-2026-0001', $this->value($xp, $h.'inv:text'));
    }

    public function test_vat_payer_invoice_maps_rates_to_bands(): void
    {
        $invoice = $this->invoice('vat', [
            'number' => 'FA-2026-0002',
            'is_vat_payer' => true,
        ], [
            ['description' => 'High', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 23, 'line_base' => 100, 'line_vat' => 23, 'line_total' => 123],
            ['description' => 'Low', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 19, 'line_base' => 100, 'line_vat' => 19, 'line_total' => 119],
            ['description' => 'Third', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 5, 'line_base' => 100, 'line_vat' => 5, 'line_total' => 105],
            ['description' => 'Zero', 'quantity' => 1, 'unit_price' => 10, 'vat_rate' => 0, 'line_base' => 10, 'line_vat' => 0, 'line_total' => 10],
        ]);

        $xp = $this->build([$invoice]);

        $this->assertSame(['high', 'low', 'third', 'none'], $this->texts($xp, '//inv:invoiceItem/inv:rateVAT'));
        $this->assertSame('23.00', $this->value($xp, '//inv:invoiceItem[1]/inv:homeCurrency/typ:priceVAT'));
        $this->assertSame('123.00', $this->value($xp, '//inv:invoiceItem[1]/inv:homeCurrency/typ:priceSum'));

        $s = '//inv:invoiceSummary/inv:homeCurrency/';
        $this->assertSame('10.00', $this->value($xp, $s.'typ:priceNone'));
        $this->assertSame('100.00', $this->value($xp, $s.'typ:priceHigh'));
        $this->assertSame('23.00', $this->value($xp, $s.'typ:priceHighVAT'));
        $this->assertSame('123.00', $this->value($xp, $s.'typ:priceHighSum'));
        $this->assertSame('19.00', $this->value($xp, $s.'typ:priceLowVAT'));
        $this->assertSame('100.00', $this->value($xp, $s.'typ:price3'));
        $this->assertSame('5.00', $this->value($xp, $s.'typ:price3VAT'));
    }

    public function test_vat_band_map_is_configurable(): void
    {
        config(['pohoda.vat_band_map' => [20 => 'high', 10 => 'low', 0 => 'none']]);

        $invoice = $this->invoice('vat', ['number' => 'FA-2026-0003', 'is_vat_payer' => true], [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 20, 'line_base' => 100, 'line_vat' => 20, 'line_total' => 120],
        ]);

        $this->assertSame('high', $this->value($this->build([$invoice]), '//inv:invoiceItem/inv:rateVAT'));
    }

    public function test_credit_note_is_exported_with_positive_amounts_and_references_original(): void
    {
        $original = $this->invoice('cancelled', ['number' => 'FA-2026-0010'], [
            ['description' => 'Service', 'quantity' => 2, 'unit_price' => 40, 'vat_rate' => 0, 'line_base' => 80, 'line_vat' => 0, 'line_total' => 80],
        ]);
        $credit = $this->invoice('issued', [
            'number' => 'DB-2026-0001',
            'credited_invoice_id' => $original->id,
            'subtotal' => '-80.00',
            'total' => '-80.00',
        ], [
            ['description' => 'Service', 'quantity' => 2, 'unit_price' => -40, 'vat_rate' => 0, 'line_base' => -80, 'line_vat' => 0, 'line_total' => -80],
        ]);

        $xp = $this->build([$original, $credit]);

        $this->assertSame(
            ['issuedInvoice', 'issuedCreditNotice'],
            $this->texts($xp, '//inv:invoiceType'),
        );
        $item = '//dat:dataPackItem[@id="DB-2026-0001"]//inv:invoiceItem/';
        $this->assertSame('2.00', $this->value($xp, $item.'inv:quantity'));
        $this->assertSame('40.00', $this->value($xp, $item.'inv:homeCurrency/typ:unitPrice'));
        $this->assertSame('80.00', $this->value($xp, $item.'inv:homeCurrency/typ:price'));
        $this->assertSame('80.00', $this->value($xp, '//dat:dataPackItem[@id="DB-2026-0001"]//typ:priceNone'));
        $this->assertStringContainsString('FA-2026-0010', (string) $this->value($xp, '//dat:dataPackItem[@id="DB-2026-0001"]//inv:invoiceHeader/inv:text'));
        $this->assertSame('FA-2026-0010', $this->value($xp, '//dat:dataPackItem[@id="DB-2026-0001"]//inv:originalDocumentNumber'));
    }

    public function test_deposit_is_noted_in_header_and_total_is_untouched(): void
    {
        $invoice = $this->invoice('issued', ['number' => 'FA-2026-0004', 'deposit' => '30.00'], [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 0, 'line_base' => 100, 'line_vat' => 0, 'line_total' => 100],
        ]);

        $xp = $this->build([$invoice]);

        $this->assertStringContainsString('30.00 EUR', (string) $this->value($xp, '//inv:invoiceHeader/inv:note'));
        $this->assertSame('100.00', $this->value($xp, '//typ:priceNone'));
    }

    public function test_document_rounding_uses_math2one_and_round_element(): void
    {
        $invoice = $this->invoice('issued', [
            'number' => 'FA-2026-0005',
            'rounding_mode' => RoundingModeEnum::Document,
            'rounding_amount' => '0.40',
        ], [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 99.6, 'vat_rate' => 0, 'line_base' => 99.6, 'line_vat' => 0, 'line_total' => 99.6],
        ]);

        $xp = $this->build([$invoice]);

        $this->assertSame('math2one', $this->value($xp, '//inv:roundingDocument'));
        $this->assertSame('0.40', $this->value($xp, '//typ:round/typ:priceRound'));
        $this->assertSame(1, $this->nodeCount($xp, '//inv:invoiceItem'));
    }

    public function test_cash_rounding_is_emitted_as_last_item(): void
    {
        $invoice = $this->invoice('issued', [
            'number' => 'FA-2026-0006',
            'rounding_mode' => RoundingModeEnum::Cash005,
            'rounding_amount' => '-0.02',
        ], [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 10.02, 'vat_rate' => 0, 'line_base' => 10.02, 'line_vat' => 0, 'line_total' => 10.02],
        ]);

        $xp = $this->build([$invoice]);

        $this->assertSame('none', $this->value($xp, '//inv:roundingDocument'));
        $this->assertSame(2, $this->nodeCount($xp, '//inv:invoiceItem'));
        $this->assertSame('Zaokrúhlenie', $this->value($xp, '//inv:invoiceItem[2]/inv:text'));
        $this->assertSame('-0.02', $this->value($xp, '//inv:invoiceItem[2]/inv:homeCurrency/typ:priceSum'));
        $this->assertSame('10.00', $this->value($xp, '//typ:priceNone'));
    }

    public function test_non_eur_invoices_are_skipped_and_listed_in_data_pack_note(): void
    {
        $eur = $this->invoice('issued', ['number' => 'FA-2026-0007']);
        $czk = $this->invoice('issued', ['number' => 'FA-2026-0008', 'currency' => CurrencyEnum::CZK]);

        $xp = $this->build([$eur, $czk]);

        $this->assertSame(1, $this->nodeCount($xp, '//dat:dataPackItem'));
        $this->assertStringContainsString('FA-2026-0008', (string) $this->value($xp, '/dat:dataPack/@note'));
    }

    public function test_nothing_exportable_is_rejected_because_schema_requires_an_item(): void
    {
        $czk = $this->invoice('issued', ['number' => 'FA-2026-0011', 'currency' => CurrencyEnum::CZK]);

        $this->expectException(ValidationException::class);

        app(PohodaXmlBuilder::class)->build($this->tenant, new Collection([$czk]), '2026-09-01', '2026-09-30');
    }

    public function test_windows_1250_encoding_is_declared_and_converted(): void
    {
        config(['pohoda.encoding' => 'windows-1250']);
        $invoice = $this->invoice('issued', ['number' => 'FA-2026-0009', 'customer_name' => 'Žltý kôň']);

        $xml = app(PohodaXmlBuilder::class)->build($this->tenant, new Collection([$invoice]), '2026-09-01', '2026-09-30');

        $this->assertStringContainsString('encoding="windows-1250"', $xml);
        $this->assertStringContainsString((string) iconv('UTF-8', 'WINDOWS-1250', 'Žltý kôň'), $xml);
        $this->assertStringNotContainsString('Žltý', $xml);
    }
}
