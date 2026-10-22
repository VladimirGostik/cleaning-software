<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Data\Invoices\PohodaExportData;
use App\Data\Invoices\PohodaExportPreviewData;
use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use XMLWriter;

final readonly class PohodaXmlBuilder
{
    private const array EXPORTED_STATUSES = [
        InvoiceStatusEnum::Issued,
        InvoiceStatusEnum::Overdue,
        InvoiceStatusEnum::Paid,
        InvoiceStatusEnum::Cancelled,
    ];

    private const array BAND_ELEMENTS = [
        'low' => ['priceLow', 'priceLowVAT', 'priceLowSum'],
        'high' => ['priceHigh', 'priceHighVAT', 'priceHighSum'],
        'third' => ['price3', 'price3VAT', 'price3Sum'],
    ];

    public function export(Tenant $tenant, PohodaExportData $range): string
    {
        $this->assertHasIco($tenant);

        return $this->build($tenant, $this->invoicesFor($tenant, $range), $range->from, $range->to);
    }

    public function preview(Tenant $tenant, PohodaExportData $range): PohodaExportPreviewData
    {
        $this->assertHasIco($tenant);
        $invoices = $this->invoicesFor($tenant, $range);
        $skipped = $invoices->filter(fn (Invoice $invoice): bool => $invoice->currency !== CurrencyEnum::EUR)->count();

        return new PohodaExportPreviewData($invoices->count() - $skipped, $skipped);
    }

    /** @param Collection<int, Invoice> $invoices */
    public function build(Tenant $tenant, Collection $invoices, string $from, string $to): string
    {
        $ico = $this->icoDigits($tenant);
        $exported = $invoices->filter(fn (Invoice $invoice): bool => $invoice->currency === CurrencyEnum::EUR);
        $skipped = $invoices->reject(fn (Invoice $invoice): bool => $invoice->currency === CurrencyEnum::EUR);
        $encoding = config()->string('pohoda.encoding');

        if ($exported->isEmpty()) {
            throw ValidationException::withMessages(['from' => __('app.pohoda_export_empty')]);
        }

        $w = new XMLWriter;
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', $encoding);

        $w->startElement('dat:dataPack');
        foreach (config()->array('pohoda.namespaces') as $prefix => $uri) {
            $w->writeAttribute('xmlns:'.$prefix, is_string($uri) ? $uri : '');
        }
        $w->writeAttribute('id', 'cleanmaster-'.$ico.'-'.$from.'-'.$to);
        $w->writeAttribute('ico', $ico);
        $w->writeAttribute('application', config()->string('pohoda.application'));
        $w->writeAttribute('version', config()->string('pohoda.version'));
        $w->writeAttribute('note', $this->dataPackNote($skipped, $from, $to));

        foreach ($exported as $invoice) {
            $w->startElement('dat:dataPackItem');
            $w->writeAttribute('id', (string) $invoice->number);
            $w->writeAttribute('version', config()->string('pohoda.version'));
            $this->writeInvoice($w, $invoice);
            $w->endElement();
        }

        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    /** @return Collection<int, Invoice> */
    private function invoicesFor(Tenant $tenant, PohodaExportData $range): Collection
    {
        /** @var Builder<Invoice> $query */
        $query = Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', self::EXPORTED_STATUSES)
            ->whereNotNull('number')
            ->whereDate('issue_date', '>=', $range->from)
            ->whereDate('issue_date', '<=', $range->to);

        if ($query->count() > config()->integer('pohoda.max_invoices')) {
            throw ValidationException::withMessages(['from' => __('app.pohoda_export_too_many')]);
        }

        $invoices = $query->with(['items', 'creditedInvoice'])->orderBy('issue_date')->orderBy('issued_at')->orderBy('number')->get();
        $this->assertRatesMapped($invoices);

        return $invoices;
    }

    /** @param Collection<int, Invoice> $invoices */
    private function assertRatesMapped(Collection $invoices): void
    {
        $map = config()->array('pohoda.vat_band_map');
        $unmapped = [];

        foreach ($invoices as $invoice) {
            if (! $invoice->is_vat_payer || $invoice->currency !== CurrencyEnum::EUR) {
                continue;
            }

            foreach ($invoice->items as $item) {
                if (! is_string($map[(int) round((float) $item->vat_rate)] ?? null)) {
                    $unmapped[(string) $invoice->number] = (int) round((float) $item->vat_rate);
                }
            }
        }

        if ($unmapped !== []) {
            throw ValidationException::withMessages(['from' => __('app.pohoda_export_unmapped_vat_rate', [
                'rates' => implode(', ', array_unique(array_values($unmapped))).' %',
                'numbers' => implode(', ', array_keys($unmapped)),
            ])]);
        }
    }

    private function assertHasIco(Tenant $tenant): void
    {
        if ($this->icoDigits($tenant) === '') {
            throw ValidationException::withMessages(['from' => __('app.pohoda_export_requires_ico')]);
        }
    }

    private function icoDigits(Tenant $tenant): string
    {
        return (string) preg_replace('/\D/', '', (string) $tenant->ico);
    }

    /** @param Collection<int, Invoice> $skipped */
    private function dataPackNote(Collection $skipped, string $from, string $to): string
    {
        $note = config()->string('pohoda.application').' '.$from.' - '.$to;

        if ($skipped->isEmpty()) {
            return $note;
        }

        return $note.'. '.__('app.pohoda_note_skipped_non_eur', ['numbers' => $skipped->pluck('number')->implode(', ')], 'sk');
    }

    private function writeInvoice(XMLWriter $w, Invoice $invoice): void
    {
        $w->startElement('inv:invoice');
        $w->writeAttribute('version', config()->string('pohoda.version'));
        $this->writeHeader($w, $invoice);
        $this->writeDetail($w, $invoice);
        $this->writeSummary($w, $invoice);
        $w->endElement();
    }

    private function writeHeader(XMLWriter $w, Invoice $invoice): void
    {
        $credit = $invoice->isCreditNote();

        $w->startElement('inv:invoiceHeader');
        $this->text($w, 'inv:invoiceType', $credit ? 'issuedCreditNotice' : 'issuedInvoice');

        $w->startElement('inv:number');
        $this->text($w, 'typ:numberRequested', (string) $invoice->number);
        $w->endElement();

        $this->optional($w, 'inv:symVar', $invoice->variable_symbol);
        $this->optional($w, 'inv:originalDocumentNumber', $invoice->creditedInvoice?->number);
        $this->text($w, 'inv:date', $invoice->issue_date->toDateString());
        $this->text($w, 'inv:dateTax', $invoice->delivery_date->toDateString());
        $this->text($w, 'inv:dateAccounting', $invoice->issue_date->toDateString());
        $this->text($w, 'inv:dateDue', $invoice->due_date->toDateString());
        $this->text($w, 'inv:text', $this->headerText($invoice));
        $this->writePartner($w, $invoice);

        $w->startElement('inv:paymentType');
        $this->text($w, 'typ:paymentType', $this->paymentType($invoice->payment_type));
        $w->endElement();

        $this->optional($w, 'inv:symConst', $invoice->constant_symbol);
        $this->optional($w, 'inv:symSpec', $invoice->specific_symbol);

        $note = $this->headerNote($invoice);
        $this->optional($w, 'inv:note', $note);

        $w->endElement();
    }

    private function headerText(Invoice $invoice): string
    {
        $original = $invoice->creditedInvoice?->number;

        if ($original !== null) {
            return __('app.pohoda_text_credit_note', ['number' => $original], 'sk');
        }

        return __('app.pohoda_text_invoice', ['number' => (string) $invoice->number], 'sk');
    }

    private function headerNote(Invoice $invoice): ?string
    {
        $deposit = $this->signed($invoice, $invoice->deposit);

        if (abs((float) $deposit) < 0.005) {
            return null;
        }

        return __('app.pohoda_note_deposit', ['amount' => $deposit, 'currency' => $invoice->currency->value], 'sk');
    }

    private function writePartner(XMLWriter $w, Invoice $invoice): void
    {
        $w->startElement('inv:partnerIdentity');
        $w->startElement('typ:address');
        $this->text($w, 'typ:company', $invoice->customer_name);
        $this->optional($w, 'typ:city', $invoice->customer_city);
        $this->optional($w, 'typ:street', $invoice->customer_street);
        $this->optional($w, 'typ:zip', $invoice->customer_postal_code);
        $this->optional($w, 'typ:ico', $invoice->customer_ico);
        $this->optional($w, 'typ:dic', $invoice->customer_dic);
        $this->optional($w, 'typ:icDph', $invoice->customer_vat_number);

        if (strlen((string) $invoice->customer_country) === 2) {
            $w->startElement('typ:country');
            $this->text($w, 'typ:ids', strtoupper((string) $invoice->customer_country));
            $w->endElement();
        }

        $w->endElement();
        $w->endElement();
    }

    private function writeDetail(XMLWriter $w, Invoice $invoice): void
    {
        $w->startElement('inv:invoiceDetail');

        foreach ($invoice->items as $item) {
            $this->writeItem($w, $invoice, $item);
        }

        $rounding = $this->cashRounding($invoice);
        if ($rounding !== null) {
            $this->writeRoundingItem($w, $rounding);
        }

        $w->endElement();
    }

    private function writeItem(XMLWriter $w, Invoice $invoice, InvoiceItem $item): void
    {
        $band = $this->band($invoice, $item->vat_rate);

        $w->startElement('inv:invoiceItem');
        $this->text($w, 'inv:text', $item->description);
        $this->text($w, 'inv:quantity', $this->amount((float) $item->quantity));
        $this->optional($w, 'inv:unit', $item->unit);
        $this->text($w, 'inv:payVAT', 'false');
        $this->text($w, 'inv:rateVAT', $band);

        if ((float) $item->discount_percent > 0) {
            $this->text($w, 'inv:discountPercentage', $this->amount((float) $item->discount_percent));
        }

        $w->startElement('inv:homeCurrency');
        $this->text($w, 'typ:unitPrice', $this->signed($invoice, $item->unit_price));
        $this->text($w, 'typ:price', $this->signed($invoice, $item->line_base));
        $this->text($w, 'typ:priceVAT', $this->signed($invoice, $invoice->is_vat_payer ? $item->line_vat : 0));
        $this->text($w, 'typ:priceSum', $this->signed($invoice, $invoice->is_vat_payer ? $item->line_total : $item->line_base));
        $w->endElement();

        $w->endElement();
    }

    private function writeRoundingItem(XMLWriter $w, string $amount): void
    {
        $w->startElement('inv:invoiceItem');
        $this->text($w, 'inv:text', __('app.pohoda_rounding_item', [], 'sk'));
        $this->text($w, 'inv:quantity', '1.00');
        $this->text($w, 'inv:payVAT', 'false');
        $this->text($w, 'inv:rateVAT', 'none');
        $w->startElement('inv:homeCurrency');
        $this->text($w, 'typ:unitPrice', $amount);
        $this->text($w, 'typ:price', $amount);
        $this->text($w, 'typ:priceVAT', '0.00');
        $this->text($w, 'typ:priceSum', $amount);
        $w->endElement();
        $w->endElement();
    }

    private function writeSummary(XMLWriter $w, Invoice $invoice): void
    {
        $document = $invoice->rounding_mode === RoundingModeEnum::Document;
        $cashRounding = $this->cashRounding($invoice);

        $w->startElement('inv:invoiceSummary');
        $this->text($w, 'inv:roundingDocument', $document ? 'math2one' : 'none');

        $w->startElement('inv:homeCurrency');

        $bands = $this->bandTotals($invoice);
        $none = $bands['none']['base'] + ($cashRounding !== null ? (float) $cashRounding : 0.0);
        if (abs($none) >= 0.005 || count($bands) === 1) {
            $this->text($w, 'typ:priceNone', $this->amount($none));
        }

        foreach (self::BAND_ELEMENTS as $band => [$baseElement, $vatElement, $sumElement]) {
            if (! isset($bands[$band])) {
                continue;
            }

            $this->text($w, 'typ:'.$baseElement, $this->amount($bands[$band]['base']));
            $this->text($w, 'typ:'.$vatElement, $this->amount($bands[$band]['vat']));
            $this->text($w, 'typ:'.$sumElement, $this->amount($bands[$band]['base'] + $bands[$band]['vat']));
        }

        if ($document) {
            $w->startElement('typ:round');
            $this->text($w, 'typ:priceRound', $this->signed($invoice, $invoice->rounding_amount));
            $w->endElement();
        }

        $w->endElement();
        $w->endElement();
    }

    /** @return array<string, array{base: float, vat: float}> */
    private function bandTotals(Invoice $invoice): array
    {
        $totals = ['none' => ['base' => 0.0, 'vat' => 0.0]];
        $sign = $invoice->isCreditNote() ? -1.0 : 1.0;

        foreach ($invoice->items as $item) {
            $band = $this->band($invoice, $item->vat_rate);
            $totals[$band] ??= ['base' => 0.0, 'vat' => 0.0];
            $totals[$band]['base'] += $sign * (float) $item->line_base;
            $totals[$band]['vat'] += $invoice->is_vat_payer ? $sign * (float) $item->line_vat : 0.0;
        }

        return array_map(fn (array $t): array => ['base' => round($t['base'], 2), 'vat' => round($t['vat'], 2)], $totals);
    }

    private function cashRounding(Invoice $invoice): ?string
    {
        if ($invoice->rounding_mode !== RoundingModeEnum::Cash005 || abs((float) $invoice->rounding_amount) < 0.005) {
            return null;
        }

        return $this->signed($invoice, $invoice->rounding_amount);
    }

    private function band(Invoice $invoice, string|float $rate): string
    {
        if (! $invoice->is_vat_payer) {
            return 'none';
        }

        $band = config()->array('pohoda.vat_band_map')[(int) round((float) $rate)] ?? null;

        return is_string($band) ? $band : throw new DomainException('No Pohoda VAT band mapped for rate '.$rate);
    }

    private function paymentType(PaymentTypeEnum $type): string
    {
        return match ($type) {
            PaymentTypeEnum::Cash => 'cash',
            PaymentTypeEnum::Card => 'creditcard',
            PaymentTypeEnum::CashOnDelivery => 'delivery',
            PaymentTypeEnum::Transfer, PaymentTypeEnum::Other => 'draft',
        };
    }

    private function signed(Invoice $invoice, string|float|int $value): string
    {
        return $this->amount(($invoice->isCreditNote() ? -1.0 : 1.0) * (float) $value);
    }

    private function amount(float $value): string
    {
        $rounded = round($value, 2);

        return number_format($rounded == 0.0 ? 0.0 : $rounded, 2, '.', '');
    }

    private function text(XMLWriter $w, string $name, string $value): void
    {
        $w->writeElement($name, $value);
    }

    private function optional(XMLWriter $w, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $w->writeElement($name, $value);
        }
    }
}
