<?php

declare(strict_types=1);

namespace App\Services\Efa;

use App\Enums\PaymentTypeEnum;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use XMLWriter;

/**
 * @phpstan-type Party array{name: ?string, endpoint: array{0: string, 1: string}|null, street: ?string, city: ?string, zip: ?string, country: ?string, vat_number: ?string, company_id: ?string, phone: ?string, email: ?string}
 */
final readonly class UblInvoiceXmlBuilder
{
    private const string NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const string NS_CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';

    private const string NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const string NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    /** @return list<string> */
    public function readiness(Invoice $invoice): array
    {
        $missing = [];
        $blank = static fn (?string $value): bool => $value === null || trim($value) === '';

        foreach ([
            'number' => $invoice->number,
            'supplier_name' => $invoice->supplier_name,
            'supplier_ico' => $invoice->supplier_ico,
            'supplier_address_line' => $invoice->supplier_address_line,
            'supplier_city' => $invoice->supplier_city,
            'supplier_postal_code' => $invoice->supplier_postal_code,
            'supplier_country' => $invoice->supplier_country,
        ] as $field => $value) {
            if ($blank($value)) {
                $missing[] = $field;
            }
        }

        if ($invoice->is_vat_payer && $blank($invoice->supplier_vat_number)) {
            $missing[] = 'supplier_vat_number';
        }

        if ($invoice->payment_type === PaymentTypeEnum::Transfer && $blank($invoice->supplier_iban)) {
            $missing[] = 'supplier_iban';
        }

        foreach (['customer_name' => $invoice->customer_name, 'customer_country' => $invoice->customer_country] as $field => $value) {
            if ($blank($value)) {
                $missing[] = $field;
            }
        }

        if ($blank($invoice->customer_ico) && $blank($invoice->customer_peppol_participant_id)) {
            $missing[] = 'customer_ico';
        }

        $invoice->loadMissing('items');
        if ($invoice->items->isEmpty()) {
            $missing[] = 'items';
        }

        return $missing;
    }

    public function build(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'creditedInvoice']);

        $credit = $invoice->isCreditNote();
        $figures = $this->figures($invoice);
        $currency = $invoice->currency->value;

        $w = new XMLWriter;
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');

        $w->startElement($credit ? 'CreditNote' : 'Invoice');
        $w->writeAttribute('xmlns', $credit ? self::NS_CREDIT_NOTE : self::NS_INVOICE);
        $w->writeAttribute('xmlns:cac', self::NS_CAC);
        $w->writeAttribute('xmlns:cbc', self::NS_CBC);

        $this->el($w, 'cbc:CustomizationID', config()->string('efa.customization_id'));
        $this->el($w, 'cbc:ProfileID', config()->string('efa.profile_id'));
        $this->el($w, 'cbc:ID', (string) $invoice->number);
        $this->el($w, 'cbc:IssueDate', $invoice->issue_date->toDateString());

        if ($credit) {
            $this->el($w, 'cbc:CreditNoteTypeCode', '381');
        } else {
            $this->el($w, 'cbc:DueDate', $invoice->due_date->toDateString());
            $this->el($w, 'cbc:InvoiceTypeCode', '380');
        }

        if ($invoice->note !== null && trim($invoice->note) !== '') {
            $this->el($w, 'cbc:Note', $invoice->note);
        }

        $this->el($w, 'cbc:DocumentCurrencyCode', $currency);
        $this->el($w, 'cbc:BuyerReference', (string) $invoice->number);

        if ($invoice->period_from !== null && $invoice->period_to !== null) {
            $w->startElement('cac:InvoicePeriod');
            $this->el($w, 'cbc:StartDate', $invoice->period_from->toDateString());
            $this->el($w, 'cbc:EndDate', $invoice->period_to->toDateString());
            $w->endElement();
        }

        if ($credit && $invoice->creditedInvoice !== null) {
            $w->startElement('cac:BillingReference');
            $w->startElement('cac:InvoiceDocumentReference');
            $this->el($w, 'cbc:ID', (string) $invoice->creditedInvoice->number);
            $this->el($w, 'cbc:IssueDate', $invoice->creditedInvoice->issue_date->toDateString());
            $w->endElement();
            $w->endElement();
        }

        $this->writeParty($w, 'cac:AccountingSupplierParty', $this->supplier($invoice));
        $this->writeParty($w, 'cac:AccountingCustomerParty', $this->customer($invoice));

        $w->startElement('cac:Delivery');
        $this->el($w, 'cbc:ActualDeliveryDate', $invoice->delivery_date->toDateString());
        $w->endElement();

        $this->writePaymentMeans($w, $invoice);
        $this->writeTaxTotal($w, $figures, $currency);
        $this->writeMonetaryTotal($w, $figures, $currency);

        foreach ($figures['lines'] as $index => $line) {
            $this->writeLine($w, $credit, $index + 1, $line, $currency);
        }

        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    /**
     * @return array{
     *     lines: list<array{item: InvoiceItem, quantity: float, unit_price: float, base: float, gross: float, category: string, rate: float}>,
     *     groups: list<array{category: string, rate: float, base: float, vat: float}>,
     *     line_extension: float, tax_total: float, tax_inclusive: float, prepaid: float, rounding: float, payable: float
     * }
     */
    private function figures(Invoice $invoice): array
    {
        $sign = $invoice->isCreditNote() ? -1.0 : 1.0;
        $payer = $invoice->is_vat_payer;
        $lines = [];
        $groups = [];

        foreach ($invoice->items as $item) {
            $rate = $payer ? (float) $item->vat_rate : 0.0;
            $category = ! $payer ? 'O' : ($rate > 0 ? 'S' : 'Z');
            $base = round($sign * (float) $item->line_base, 2);
            $vat = $payer ? round($sign * (float) $item->line_vat, 2) : 0.0;
            $unitPrice = round($sign * (float) $item->unit_price, 2);

            $lines[] = [
                'item' => $item,
                'quantity' => (float) $item->quantity,
                'unit_price' => $unitPrice,
                'base' => $base,
                'gross' => round((float) $item->quantity * $unitPrice, 2),
                'category' => $category,
                'rate' => $rate,
            ];

            $key = $category.'|'.$rate;
            $groups[$key] ??= ['category' => $category, 'rate' => $rate, 'base' => 0.0, 'vat' => 0.0];
            $groups[$key]['base'] = round($groups[$key]['base'] + $base, 2);
            $groups[$key]['vat'] = round($groups[$key]['vat'] + $vat, 2);
        }

        $groups = array_values($groups);
        usort($groups, static fn (array $a, array $b): int => $b['rate'] <=> $a['rate']);

        $lineExtension = round(array_sum(array_column($groups, 'base')), 2);
        $taxTotal = round(array_sum(array_column($groups, 'vat')), 2);
        $taxInclusive = round($lineExtension + $taxTotal, 2);
        $prepaid = round($sign * (float) $invoice->deposit, 2);
        $rounding = round($sign * (float) $invoice->rounding_amount, 2);

        return [
            'lines' => $lines,
            'groups' => $groups,
            'line_extension' => $lineExtension,
            'tax_total' => $taxTotal,
            'tax_inclusive' => $taxInclusive,
            'prepaid' => $prepaid,
            'rounding' => $rounding,
            'payable' => round($taxInclusive - $prepaid + $rounding, 2),
        ];
    }

    /** @return Party */
    private function supplier(Invoice $invoice): array
    {
        return [
            'name' => $invoice->supplier_name,
            'endpoint' => $this->endpoint($invoice->supplier_peppol_participant_id, $invoice->supplier_ico, null),
            'street' => $invoice->supplier_address_line,
            'city' => $invoice->supplier_city,
            'zip' => $invoice->supplier_postal_code,
            'country' => $invoice->supplier_country,
            'vat_number' => $invoice->is_vat_payer ? $invoice->supplier_vat_number : null,
            'company_id' => $invoice->supplier_ico,
            'phone' => $invoice->supplier_contact_phone,
            'email' => $invoice->supplier_contact_email,
        ];
    }

    /** @return Party */
    private function customer(Invoice $invoice): array
    {
        return [
            'name' => $invoice->customer_name,
            'endpoint' => $this->endpoint($invoice->customer_peppol_participant_id, $invoice->customer_ico, $invoice->customer_email),
            'street' => $invoice->customer_street,
            'city' => $invoice->customer_city,
            'zip' => $invoice->customer_postal_code,
            'country' => $invoice->customer_country,
            'vat_number' => $invoice->customer_vat_number,
            'company_id' => $invoice->customer_ico,
            'phone' => null,
            'email' => $invoice->customer_email,
        ];
    }

    /** @return array{0: string, 1: string}|null */
    private function endpoint(?string $participantId, ?string $ico, ?string $email): ?array
    {
        if ($participantId !== null && preg_match('/^(\d{4}):(.+)$/', trim($participantId), $m) === 1) {
            return [$m[1], $m[2]];
        }

        if ($ico !== null && trim($ico) !== '') {
            return [config()->string('efa.seller_scheme_id'), trim($ico)];
        }

        if ($email !== null && trim($email) !== '') {
            return [config()->string('efa.email_endpoint_scheme_id'), trim($email)];
        }

        return null;
    }

    /** @param Party $party */
    private function writeParty(XMLWriter $w, string $wrapper, array $party): void
    {
        $w->startElement($wrapper);
        $w->startElement('cac:Party');

        if ($party['endpoint'] !== null) {
            $this->el($w, 'cbc:EndpointID', $party['endpoint'][1], ['schemeID' => $party['endpoint'][0]]);
        }

        $w->startElement('cac:PartyName');
        $this->el($w, 'cbc:Name', $this->str($party['name']));
        $w->endElement();

        $w->startElement('cac:PostalAddress');
        $this->optional($w, 'cbc:StreetName', $party['street']);
        $this->optional($w, 'cbc:CityName', $party['city']);
        $this->optional($w, 'cbc:PostalZone', $party['zip']);
        $w->startElement('cac:Country');
        $this->el($w, 'cbc:IdentificationCode', strtoupper($this->str($party['country'])));
        $w->endElement();
        $w->endElement();

        $vat = $this->str($party['vat_number']);
        if (trim($vat) !== '') {
            $w->startElement('cac:PartyTaxScheme');
            $this->el($w, 'cbc:CompanyID', trim($vat));
            $w->startElement('cac:TaxScheme');
            $this->el($w, 'cbc:ID', config()->string('efa.vat_scheme_id'));
            $w->endElement();
            $w->endElement();
        }

        $w->startElement('cac:PartyLegalEntity');
        $this->el($w, 'cbc:RegistrationName', $this->str($party['name']));
        $companyId = $this->str($party['company_id']);
        if (trim($companyId) !== '') {
            $this->el($w, 'cbc:CompanyID', trim($companyId), ['schemeID' => config()->string('efa.seller_scheme_id')]);
        }
        $w->endElement();

        if ($this->str($party['phone']) !== '' || $this->str($party['email']) !== '') {
            $w->startElement('cac:Contact');
            $this->optional($w, 'cbc:Telephone', $party['phone']);
            $this->optional($w, 'cbc:ElectronicMail', $party['email']);
            $w->endElement();
        }

        $w->endElement();
        $w->endElement();
    }

    private function writePaymentMeans(XMLWriter $w, Invoice $invoice): void
    {
        $code = config()->array('efa.payment_means_map')[$invoice->payment_type->value] ?? 'ZZZ';
        $code = is_string($code) ? $code : 'ZZZ';
        $creditTransfer = $invoice->payment_type === PaymentTypeEnum::Transfer;

        $w->startElement('cac:PaymentMeans');
        $this->el($w, 'cbc:PaymentMeansCode', $code);
        $this->optional($w, 'cbc:PaymentID', $invoice->variable_symbol ?? $invoice->number);

        if ($creditTransfer && $invoice->supplier_iban !== null && trim($invoice->supplier_iban) !== '') {
            $w->startElement('cac:PayeeFinancialAccount');
            $this->el($w, 'cbc:ID', str_replace(' ', '', $invoice->supplier_iban));
            if ($invoice->supplier_swift !== null && trim($invoice->supplier_swift) !== '') {
                $w->startElement('cac:FinancialInstitutionBranch');
                $this->el($w, 'cbc:ID', trim($invoice->supplier_swift));
                $w->endElement();
            }
            $w->endElement();
        }

        $w->endElement();
    }

    /** @param array<string, mixed> $figures */
    private function writeTaxTotal(XMLWriter $w, array $figures, string $currency): void
    {
        /** @var list<array{category: string, rate: float, base: float, vat: float}> $groups */
        $groups = $figures['groups'];
        /** @var float $taxTotal */
        $taxTotal = $figures['tax_total'];

        $w->startElement('cac:TaxTotal');
        $this->amount($w, 'cbc:TaxAmount', $taxTotal, $currency);

        foreach ($groups as $group) {
            $w->startElement('cac:TaxSubtotal');
            $this->amount($w, 'cbc:TaxableAmount', $group['base'], $currency);
            $this->amount($w, 'cbc:TaxAmount', $group['vat'], $currency);
            $this->writeTaxCategory($w, 'cac:TaxCategory', $group['category'], $group['rate'], true);
            $w->endElement();
        }

        $w->endElement();
    }

    private function writeTaxCategory(XMLWriter $w, string $name, string $category, float $rate, bool $withExemption): void
    {
        $w->startElement($name);
        $this->el($w, 'cbc:ID', $category);

        if ($category !== 'O') {
            $this->el($w, 'cbc:Percent', $this->decimal($rate));
        }

        if ($category === 'O' && $withExemption) {
            $this->el($w, 'cbc:TaxExemptionReasonCode', config()->string('efa.vat_exemption_reason_code'));
            $this->el($w, 'cbc:TaxExemptionReason', __('app.efa_vat_not_subject_reason', [], 'sk'));
        }

        $w->startElement('cac:TaxScheme');
        $this->el($w, 'cbc:ID', config()->string('efa.vat_scheme_id'));
        $w->endElement();
        $w->endElement();
    }

    /** @param array<string, mixed> $figures */
    private function writeMonetaryTotal(XMLWriter $w, array $figures, string $currency): void
    {
        /** @var array{line_extension: float, tax_inclusive: float, prepaid: float, rounding: float, payable: float} $f */
        $f = $figures;

        $w->startElement('cac:LegalMonetaryTotal');
        $this->amount($w, 'cbc:LineExtensionAmount', $f['line_extension'], $currency);
        $this->amount($w, 'cbc:TaxExclusiveAmount', $f['line_extension'], $currency);
        $this->amount($w, 'cbc:TaxInclusiveAmount', $f['tax_inclusive'], $currency);
        $this->amount($w, 'cbc:PrepaidAmount', $f['prepaid'], $currency);
        $this->amount($w, 'cbc:PayableRoundingAmount', $f['rounding'], $currency);
        $this->amount($w, 'cbc:PayableAmount', $f['payable'], $currency);
        $w->endElement();
    }

    /** @param array{item: InvoiceItem, quantity: float, unit_price: float, base: float, gross: float, category: string, rate: float} $line */
    private function writeLine(XMLWriter $w, bool $credit, int $position, array $line, string $currency): void
    {
        $item = $line['item'];

        $w->startElement($credit ? 'cac:CreditNoteLine' : 'cac:InvoiceLine');
        $this->el($w, 'cbc:ID', (string) $position);
        $this->el($w, $credit ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity', $this->decimal($line['quantity']), ['unitCode' => $this->unitCode($item->unit)]);
        $this->amount($w, 'cbc:LineExtensionAmount', $line['base'], $currency);

        $difference = round($line['gross'] - $line['base'], 2);
        if (abs($difference) >= 0.005) {
            $isDiscount = (float) $item->discount_percent > 0 && $difference > 0;
            $w->startElement('cac:AllowanceCharge');
            $this->el($w, 'cbc:ChargeIndicator', $difference < 0 ? 'true' : 'false');
            $this->el($w, 'cbc:AllowanceChargeReason', __($isDiscount ? 'app.efa_line_discount' : 'app.efa_line_rounding', [], 'sk'));
            $this->amount($w, 'cbc:Amount', abs($difference), $currency);
            $w->endElement();
        }

        $w->startElement('cac:Item');
        $this->el($w, 'cbc:Name', $item->description);
        $this->writeTaxCategory($w, 'cac:ClassifiedTaxCategory', $line['category'], $line['rate'], false);
        $w->endElement();

        $w->startElement('cac:Price');
        $this->amount($w, 'cbc:PriceAmount', $line['unit_price'], $currency);
        $w->endElement();

        $w->endElement();
    }

    private function unitCode(?string $unit): string
    {
        $default = config()->string('efa.default_unit_code');

        if ($unit === null || trim($unit) === '') {
            return $default;
        }

        $needle = mb_strtolower(trim($unit));

        foreach (config()->array('efa.unit_code_map') as $key => $code) {
            if (mb_strtolower((string) $key) === $needle && is_string($code)) {
                return $code;
            }
        }

        return $default;
    }

    /** @param array<string, string> $attributes */
    private function el(XMLWriter $w, string $name, string $value, array $attributes = []): void
    {
        $w->startElement($name);
        foreach ($attributes as $attribute => $attributeValue) {
            $w->writeAttribute($attribute, $attributeValue);
        }
        $w->text($value);
        $w->endElement();
    }

    private function optional(XMLWriter $w, string $name, mixed $value): void
    {
        $text = $this->str($value);

        if (trim($text) !== '') {
            $this->el($w, $name, trim($text));
        }
    }

    private function amount(XMLWriter $w, string $name, float $value, string $currency): void
    {
        $this->el($w, $name, $this->decimal($value), ['currencyID' => $currency]);
    }

    private function decimal(float $value): string
    {
        $rounded = round($value, 2);

        return number_format($rounded == 0.0 ? 0.0 : $rounded, 2, '.', '');
    }

    private function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
