<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\RoundingModeEnum;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Services\DocumentTotalsCalculator;
use DOMDocument;
use DOMNode;
use DOMXPath;
use LibXMLError;

trait BuildsEfaInvoices
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    protected function efaInvoice(Tenant $tenant, array $attributes = [], array $items = [], bool $vatPayer = false): Invoice
    {
        $invoice = Invoice::factory()->issued()->create($attributes + [
            'tenant_id' => $tenant->id,
            'is_vat_payer' => $vatPayer,
            'issue_date' => '2026-09-10',
            'delivery_date' => '2026-09-09',
            'due_date' => '2026-09-24',
            'supplier_name' => 'Cistota s.r.o.',
            'supplier_ico' => '12345678',
            'supplier_vat_number' => $vatPayer ? 'SK2012345678' : null,
            'supplier_address_line' => 'Hlavna 1',
            'supplier_city' => 'Bratislava',
            'supplier_postal_code' => '81101',
            'supplier_country' => 'SK',
            'supplier_iban' => 'SK8975000000000123456789',
            'supplier_swift' => 'TATRSKBX',
            'customer_name' => 'Klient a.s.',
            'customer_ico' => '87654321',
            'customer_street' => 'Dlha 5',
            'customer_city' => 'Kosice',
            'customer_postal_code' => '04001',
            'customer_country' => 'SK',
        ]);

        foreach ($items as $position => $item) {
            InvoiceItem::factory()->create($item + ['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'position' => $position]);
        }

        return $this->efaRecompute($invoice);
    }

    protected function efaRecompute(Invoice $invoice): Invoice
    {
        $invoice->load('items');
        $totals = app(DocumentTotalsCalculator::class)->totals(
            $invoice->items->map(fn (InvoiceItem $item): array => $item->only(['vat_rate', 'line_base', 'line_vat', 'line_total']))->all(),
            $invoice->is_vat_payer,
            $invoice->rounding_mode ?? RoundingModeEnum::None,
        );
        $invoice->update($totals);

        return $invoice->refresh()->load('items');
    }

    protected function loadUbl(string $xml): DOMXPath
    {
        $doc = new DOMDocument;
        $this->assertTrue($doc->loadXML($xml), 'XML is not well-formed');

        $root = $doc->documentElement;
        $schema = $root?->localName === 'CreditNote' ? 'UBL-CreditNote-2.1.xsd' : 'UBL-Invoice-2.1.xsd';

        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $valid = $doc->schemaValidate(base_path('tests/Fixtures/ubl/xsd/maindoc/'.$schema));
        $messages = array_map(fn (LibXMLError $e): string => 'line '.$e->line.': '.trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        $this->assertTrue($valid, "UBL XSD validation failed:\n".implode("\n", $messages));

        $xp = new DOMXPath($doc);
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        return $xp;
    }

    protected function xv(DOMXPath $xp, string $query): ?string
    {
        $nodes = $xp->query($query);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMNode ? $node->textContent : null;
    }

    protected function xn(DOMXPath $xp, string $query): int
    {
        $nodes = $xp->query($query);

        return $nodes === false ? 0 : $nodes->length;
    }

    protected function xsum(DOMXPath $xp, string $query): float
    {
        $nodes = $xp->query($query);
        $sum = 0.0;

        foreach ($nodes === false ? [] : $nodes as $node) {
            if ($node instanceof DOMNode) {
                $sum += (float) $node->textContent;
            }
        }

        return round($sum, 2);
    }

    protected function assertEn16931Sums(DOMXPath $xp): void
    {
        $lineExtension = (float) $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:LineExtensionAmount');
        $taxExclusive = (float) $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount');
        $taxInclusive = (float) $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount');
        $prepaid = (float) $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PrepaidAmount');
        $rounding = (float) $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PayableRoundingAmount');
        $payable = (float) $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PayableAmount');
        $taxAmount = (float) $this->xv($xp, '/*/cac:TaxTotal/cbc:TaxAmount');

        $this->assertEqualsWithDelta($this->xsum($xp, '//cac:InvoiceLine/cbc:LineExtensionAmount | //cac:CreditNoteLine/cbc:LineExtensionAmount'), $lineExtension, 0.001, 'BR-CO-10');
        $this->assertEqualsWithDelta($lineExtension, $taxExclusive, 0.001, 'BR-CO-13');
        $this->assertEqualsWithDelta($taxExclusive + $taxAmount, $taxInclusive, 0.001, 'BR-CO-15');
        $this->assertEqualsWithDelta($taxInclusive - $prepaid + $rounding, $payable, 0.001, 'BR-CO-16');
        $this->assertEqualsWithDelta($this->xsum($xp, '/*/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount'), $taxAmount, 0.001, 'BR-CO-14');
        $this->assertEqualsWithDelta($lineExtension, $this->xsum($xp, '/*/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount'), 0.001, 'BR-CO-18/taxable sum');
    }
}
