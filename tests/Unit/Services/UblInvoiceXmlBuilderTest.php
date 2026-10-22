<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use App\Models\Tenant;
use App\Services\Efa\UblInvoiceXmlBuilder;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsEfaInvoices;
use Tests\TestCase;

final class UblInvoiceXmlBuilderTest extends TestCase
{
    use BuildsEfaInvoices, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->bindTenant($this->tenant);
    }

    private function builder(): UblInvoiceXmlBuilder
    {
        return app(UblInvoiceXmlBuilder::class);
    }

    public function test_non_vat_payer_invoice_uses_category_o_and_validates(): void
    {
        $invoice = $this->efaInvoice($this->tenant, ['payment_type' => PaymentTypeEnum::Transfer], [
            ['description' => 'Upratovanie', 'quantity' => 10, 'unit' => ' Hod ', 'unit_price' => 12.5, 'line_base' => 125, 'line_vat' => 0, 'line_total' => 125],
            ['description' => 'Chemia', 'quantity' => 2, 'unit' => 'zvlastna', 'unit_price' => 7.25, 'line_base' => 14.5, 'line_vat' => 0, 'line_total' => 14.5],
        ]);

        $xp = $this->loadUbl($this->builder()->build($invoice));

        $this->assertSame('Invoice', $xp->document->documentElement?->localName);
        $this->assertSame(config('efa.customization_id'), $this->xv($xp, '/*/cbc:CustomizationID'));
        $this->assertSame(config('efa.profile_id'), $this->xv($xp, '/*/cbc:ProfileID'));
        $this->assertSame('380', $this->xv($xp, '/*/cbc:InvoiceTypeCode'));
        $this->assertSame((string) $invoice->number, $this->xv($xp, '/*/cbc:ID'));
        $this->assertSame('2026-09-24', $this->xv($xp, '/*/cbc:DueDate'));
        $this->assertSame('EUR', $this->xv($xp, '/*/cbc:DocumentCurrencyCode'));

        $this->assertSame(1, $this->xn($xp, '/*/cac:TaxTotal/cac:TaxSubtotal'));
        $this->assertSame('O', $this->xv($xp, '/*/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:ID'));
        $this->assertSame('VATEX-EU-O', $this->xv($xp, '//cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReasonCode'));
        $this->assertNotEmpty($this->xv($xp, '//cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReason'));
        $this->assertSame(0, $this->xn($xp, '//cac:TaxSubtotal/cac:TaxCategory/cbc:Percent'));
        $this->assertSame('0.00', $this->xv($xp, '/*/cac:TaxTotal/cbc:TaxAmount'));
        $this->assertSame(0, $this->xn($xp, '//cac:AccountingSupplierParty//cac:PartyTaxScheme'));
        $this->assertSame(2, $this->xn($xp, '//cac:ClassifiedTaxCategory[cbc:ID="O"]'));

        $this->assertSame('HUR', $this->xv($xp, '//cac:InvoiceLine[1]/cbc:InvoicedQuantity/@unitCode'));
        $this->assertSame('C62', $this->xv($xp, '//cac:InvoiceLine[2]/cbc:InvoicedQuantity/@unitCode'));

        $this->assertSame('30', $this->xv($xp, '//cac:PaymentMeans/cbc:PaymentMeansCode'));
        $this->assertSame('SK8975000000000123456789', $this->xv($xp, '//cac:PayeeFinancialAccount/cbc:ID'));

        $this->assertSame('139.50', $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PayableAmount'));
        $this->assertSame($invoice->amount_payable, (float) $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PayableAmount'));
        $this->assertSame('EUR', $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PayableAmount/@currencyID'));
        $this->assertEn16931Sums($xp);
    }

    public function test_business_terms_are_present(): void
    {
        $invoice = $this->efaInvoice($this->tenant, [], [
            ['description' => 'Upratovanie', 'quantity' => 1, 'unit_price' => 50, 'line_base' => 50, 'line_vat' => 0, 'line_total' => 50],
        ]);

        $xp = $this->loadUbl($this->builder()->build($invoice));

        foreach ([
            'BT-1 ID' => '/*/cbc:ID',
            'BT-2 IssueDate' => '/*/cbc:IssueDate',
            'BT-5 currency' => '/*/cbc:DocumentCurrencyCode',
            'BT-10 BuyerReference' => '/*/cbc:BuyerReference',
            'BT-27 seller name' => '//cac:AccountingSupplierParty//cac:PartyLegalEntity/cbc:RegistrationName',
            'BT-30 seller company id' => '//cac:AccountingSupplierParty//cac:PartyLegalEntity/cbc:CompanyID',
            'BT-34 seller endpoint' => '//cac:AccountingSupplierParty//cbc:EndpointID',
            'BT-40 seller country' => '//cac:AccountingSupplierParty//cac:Country/cbc:IdentificationCode',
            'BT-44 buyer name' => '//cac:AccountingCustomerParty//cac:PartyLegalEntity/cbc:RegistrationName',
            'BT-49 buyer endpoint' => '//cac:AccountingCustomerParty//cbc:EndpointID',
            'BT-55 buyer country' => '//cac:AccountingCustomerParty//cac:Country/cbc:IdentificationCode',
            'BT-153 item name' => '//cac:InvoiceLine/cac:Item/cbc:Name',
            'BT-146 price' => '//cac:InvoiceLine/cac:Price/cbc:PriceAmount',
        ] as $label => $query) {
            $this->assertNotNull($this->xv($xp, $query), $label.' missing');
            $this->assertNotSame('', $this->xv($xp, $query), $label.' empty');
        }
    }

    public function test_vat_payer_multi_rate_groups_per_rate_and_matches_breakdown(): void
    {
        $invoice = $this->efaInvoice($this->tenant, [], [
            ['description' => 'A', 'quantity' => 3, 'unit_price' => 100, 'line_base' => 300, 'vat_rate' => 23, 'line_vat' => 69, 'line_total' => 369],
            ['description' => 'B', 'quantity' => 1, 'unit_price' => 80, 'line_base' => 80, 'vat_rate' => 5, 'line_vat' => 4, 'line_total' => 84],
            ['description' => 'C', 'quantity' => 1, 'unit_price' => 20, 'line_base' => 20, 'vat_rate' => 0, 'line_vat' => 0, 'line_total' => 20],
        ], vatPayer: true);

        $xp = $this->loadUbl($this->builder()->build($invoice));

        $this->assertSame(3, $this->xn($xp, '/*/cac:TaxTotal/cac:TaxSubtotal'));
        $this->assertSame('69.00', $this->xv($xp, '/*/cac:TaxTotal/cac:TaxSubtotal[cac:TaxCategory/cbc:Percent="23.00"]/cbc:TaxAmount'));
        $this->assertSame('300.00', $this->xv($xp, '/*/cac:TaxTotal/cac:TaxSubtotal[cac:TaxCategory/cbc:Percent="23.00"]/cbc:TaxableAmount'));
        $this->assertSame('S', $this->xv($xp, '/*/cac:TaxTotal/cac:TaxSubtotal[cac:TaxCategory/cbc:Percent="5.00"]/cac:TaxCategory/cbc:ID'));
        $this->assertSame('Z', $this->xv($xp, '/*/cac:TaxTotal/cac:TaxSubtotal[cac:TaxCategory/cbc:Percent="0.00"]/cac:TaxCategory/cbc:ID'));
        $this->assertSame('73.00', $this->xv($xp, '/*/cac:TaxTotal/cbc:TaxAmount'));
        $this->assertSame('SK2012345678', $this->xv($xp, '//cac:AccountingSupplierParty//cac:PartyTaxScheme/cbc:CompanyID'));
        $this->assertSame(0, $this->xn($xp, '//cbc:TaxExemptionReasonCode'));

        foreach ($invoice->vat_breakdown ?? [] as $line) {
            $rate = number_format((float) $line['rate'], 2, '.', '');
            $this->assertEqualsWithDelta((float) $line['vat'], (float) $this->xv($xp, "/*/cac:TaxTotal/cac:TaxSubtotal[cac:TaxCategory/cbc:Percent='$rate']/cbc:TaxAmount"), 0.001);
            $this->assertEqualsWithDelta((float) $line['base'], (float) $this->xv($xp, "/*/cac:TaxTotal/cac:TaxSubtotal[cac:TaxCategory/cbc:Percent='$rate']/cbc:TaxableAmount"), 0.001);
        }

        $this->assertEqualsWithDelta($invoice->amount_payable, (float) $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PayableAmount'), 0.001);
        $this->assertEn16931Sums($xp);
    }

    public function test_credit_note_has_positive_amounts_and_billing_reference(): void
    {
        $original = $this->efaInvoice($this->tenant, ['customer_peppol_participant_id' => '9950:SK1'], [
            ['description' => 'A', 'quantity' => 2, 'unit_price' => 100, 'line_base' => 200, 'vat_rate' => 23, 'line_vat' => 46, 'line_total' => 246],
        ], vatPayer: true);
        $this->actingAsTenantUser('Admin', $this->tenant);

        app(InvoiceService::class)->cancel($original, null);
        $creditNote = $original->refresh()->creditNote;
        $this->assertNotNull($creditNote);
        $this->assertTrue((float) $creditNote->total < 0);

        $xp = $this->loadUbl($this->builder()->build($creditNote));

        $this->assertSame('CreditNote', $xp->document->documentElement?->localName);
        $this->assertSame('381', $this->xv($xp, '/*/cbc:CreditNoteTypeCode'));
        $this->assertSame((string) $original->number, $this->xv($xp, '/*/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'));
        $this->assertSame('SK1', $this->xv($xp, '//cac:AccountingCustomerParty//cbc:EndpointID'));
        $this->assertSame('246.00', $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PayableAmount'));
        $this->assertSame('200.00', $this->xv($xp, '//cac:CreditNoteLine/cbc:LineExtensionAmount'));
        $this->assertSame('100.00', $this->xv($xp, '//cac:CreditNoteLine/cac:Price/cbc:PriceAmount'));
        $this->assertSame('46.00', $this->xv($xp, '/*/cac:TaxTotal/cbc:TaxAmount'));
        $this->assertSame(0, $this->xn($xp, '//cac:CreditNoteLine[starts-with(cbc:LineExtensionAmount, "-")]'));
        $this->assertEn16931Sums($xp);
    }

    public function test_deposit_becomes_prepaid_amount_and_payable_is_balance(): void
    {
        $invoice = $this->efaInvoice($this->tenant, ['deposit' => '30.00'], [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 100, 'line_base' => 100, 'line_vat' => 0, 'line_total' => 100],
        ]);

        $xp = $this->loadUbl($this->builder()->build($invoice));

        $this->assertSame('30.00', $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PrepaidAmount'));
        $this->assertSame('70.00', $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PayableAmount'));
        $this->assertEn16931Sums($xp);
    }

    public function test_rounding_amount_is_emitted_as_payable_rounding(): void
    {
        $invoice = $this->efaInvoice($this->tenant, ['rounding_mode' => RoundingModeEnum::Cash005], [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 100.03, 'line_base' => 100.03, 'line_vat' => 0, 'line_total' => 100.03],
        ]);

        $this->assertSame('0.02', $invoice->rounding_amount);

        $xp = $this->loadUbl($this->builder()->build($invoice));

        $this->assertSame('0.02', $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PayableRoundingAmount'));
        $this->assertSame('100.05', $this->xv($xp, '//cac:LegalMonetaryTotal/cbc:PayableAmount'));
        $this->assertEn16931Sums($xp);
    }

    public function test_discounted_line_keeps_line_extension_via_line_allowance(): void
    {
        $invoice = $this->efaInvoice($this->tenant, [], [
            ['description' => 'Zlava', 'quantity' => 4, 'unit_price' => 25, 'discount_percent' => 10, 'line_base' => 90, 'line_vat' => 0, 'line_total' => 90],
        ]);

        $xp = $this->loadUbl($this->builder()->build($invoice));

        $this->assertSame('false', $this->xv($xp, '//cac:InvoiceLine/cac:AllowanceCharge/cbc:ChargeIndicator'));
        $this->assertSame('10.00', $this->xv($xp, '//cac:InvoiceLine/cac:AllowanceCharge/cbc:Amount'));
        $this->assertSame('25.00', $this->xv($xp, '//cac:InvoiceLine/cac:Price/cbc:PriceAmount'));
        $this->assertEqualsWithDelta((float) $invoice->items->first()?->line_base, (float) $this->xv($xp, '//cac:InvoiceLine/cbc:LineExtensionAmount'), 0.001);
        $this->assertEn16931Sums($xp);
    }

    public function test_special_characters_are_escaped_and_xml_stays_valid(): void
    {
        $invoice = $this->efaInvoice($this->tenant, ['customer_name' => 'Tom & <Jerry> "s.r.o."'], [
            ['description' => 'A & B <c>', 'quantity' => 1, 'unit_price' => 10, 'line_base' => 10, 'line_vat' => 0, 'line_total' => 10],
        ]);

        $xp = $this->loadUbl($this->builder()->build($invoice));

        $this->assertSame('A & B <c>', $this->xv($xp, '//cac:InvoiceLine/cac:Item/cbc:Name'));
        $this->assertSame('Tom & <Jerry> "s.r.o."', $this->xv($xp, '//cac:AccountingCustomerParty//cac:PartyName/cbc:Name'));
    }

    public function test_peppol_ids_are_used_as_endpoints_and_scheme_is_split(): void
    {
        $invoice = $this->efaInvoice($this->tenant, [
            'supplier_peppol_participant_id' => '0245:12345678',
            'customer_peppol_participant_id' => '9950:SK2020000000',
            'customer_ico' => null,
        ], [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 10, 'line_base' => 10, 'line_vat' => 0, 'line_total' => 10],
        ]);

        $xp = $this->loadUbl($this->builder()->build($invoice));

        $this->assertSame('SK2020000000', $this->xv($xp, '//cac:AccountingCustomerParty//cbc:EndpointID'));
        $this->assertSame('9950', $this->xv($xp, '//cac:AccountingCustomerParty//cbc:EndpointID/@schemeID'));
        $this->assertSame('0245', $this->xv($xp, '//cac:AccountingSupplierParty//cbc:EndpointID/@schemeID'));
    }

    public function test_cash_and_card_payment_means_codes_and_no_bank_account(): void
    {
        $items = [['description' => 'A', 'quantity' => 1, 'unit_price' => 10, 'line_base' => 10, 'line_vat' => 0, 'line_total' => 10]];

        $cash = $this->loadUbl($this->builder()->build($this->efaInvoice($this->tenant, ['payment_type' => PaymentTypeEnum::Cash, 'supplier_iban' => null], $items)));
        $this->assertSame('10', $this->xv($cash, '//cac:PaymentMeans/cbc:PaymentMeansCode'));
        $this->assertSame(0, $this->xn($cash, '//cac:PayeeFinancialAccount'));

        $card = $this->loadUbl($this->builder()->build($this->efaInvoice($this->tenant, ['payment_type' => PaymentTypeEnum::Card], $items)));
        $this->assertSame('48', $this->xv($card, '//cac:PaymentMeans/cbc:PaymentMeansCode'));
        $this->assertSame(0, $this->xn($card, '//cac:PayeeFinancialAccount'));
    }

    public function test_readiness_lists_missing_fields(): void
    {
        $items = [['description' => 'A', 'quantity' => 1, 'unit_price' => 10, 'line_base' => 10, 'line_vat' => 0, 'line_total' => 10]];

        $this->assertSame([], $this->builder()->readiness($this->efaInvoice($this->tenant, [], $items)));

        $broken = $this->efaInvoice($this->tenant, [
            'supplier_ico' => null, 'supplier_city' => null, 'customer_ico' => null, 'customer_country' => null, 'supplier_iban' => null,
        ], $items);

        $this->assertEqualsCanonicalizing(
            ['supplier_ico', 'supplier_city', 'customer_ico', 'customer_country', 'supplier_iban'],
            $this->builder()->readiness($broken),
        );
    }

    public function test_readiness_requires_vat_number_for_payers_and_accepts_peppol_id_instead_of_ico(): void
    {
        $items = [['description' => 'A', 'quantity' => 1, 'unit_price' => 10, 'line_base' => 10, 'vat_rate' => 23, 'line_vat' => 2.3, 'line_total' => 12.3]];

        $payer = $this->efaInvoice($this->tenant, ['supplier_vat_number' => null], $items, vatPayer: true);
        $this->assertSame(['supplier_vat_number'], $this->builder()->readiness($payer));

        $peppolOnly = $this->efaInvoice($this->tenant, ['customer_ico' => null, 'customer_peppol_participant_id' => '0245:11111111'], $items);
        $this->assertSame([], $this->builder()->readiness($peppolOnly));
    }
}
