<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\QuoteBillingPeriodEnum;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Support\CustomerParty;
use Illuminate\Support\Str;

final readonly class QuoteDisplay
{
    public function money(Quote $quote, float|string $amount): string
    {
        return str_replace($quote->currency->symbol(), $quote->currency->value, $quote->currency->format((float) $amount));
    }

    public function quantity(QuoteItem $item): string
    {
        $quantity = (float) $item->quantity;

        if (! $item->billing_period->isRecurring()) {
            return format_quantity($quantity).'×';
        }

        $adverb = (string) $item->billing_period->adverb();

        return $quantity === 1.0 ? $adverb : format_quantity($quantity).'× '.$adverb;
    }

    /** A recurring single item names the period with the same short suffix as its line total, also when the entered unit is just the period word. */
    public function unitPrice(Quote $quote, QuoteItem $item): string
    {
        $text = $this->money($quote, $item->unit_price);
        $unit = trim((string) $item->unit);
        $suffix = $item->billing_period->shortSuffix();

        if ($suffix !== null && (float) $item->quantity === 1.0 && ($unit === '' || $this->isPeriodWord($item, $unit))) {
            return $text.' / '.$suffix;
        }

        return $unit !== '' ? $text.' / '.$unit : $text;
    }

    private function isPeriodWord(QuoteItem $item, string $unit): bool
    {
        $words = array_filter([$item->billing_period->unitNoun(), $item->billing_period->shortSuffix()]);

        return in_array(Str::lower($unit), array_map(Str::lower(...), $words), true);
    }

    public function discount(QuoteItem $item): ?string
    {
        $percent = (float) $item->discount_percent;

        return $percent > 0 ? (string) __('app.quote_pdf_discount_inline', ['percent' => format_quantity($percent)]) : null;
    }

    public function lineAmount(Quote $quote, QuoteItem $item): string
    {
        return $this->money($quote, $quote->is_vat_payer ? $item->line_base : $item->line_total);
    }

    public function lineSuffix(QuoteItem $item): ?string
    {
        return $item->billing_period->shortSuffix();
    }

    public function lineTotal(Quote $quote, QuoteItem $item): string
    {
        $suffix = $this->lineSuffix($item);

        return $this->lineAmount($quote, $item).($suffix !== null ? ' / '.$suffix : '');
    }

    /** @return list<array{period: QuoteBillingPeriodEnum, subtotal: string, vat_amount: string, total: string}> */
    public function groups(Quote $quote): array
    {
        $rows = $quote->period_totals ?? [];

        if ($rows === [] && $quote->items->isNotEmpty()) {
            $rows = [['billing_period' => QuoteBillingPeriodEnum::OneOff->value, 'subtotal' => $quote->subtotal, 'vat_amount' => $quote->vat_amount, 'total' => $quote->total]];
        }

        return array_values(array_map(static fn (array $row): array => [
            'period' => QuoteBillingPeriodEnum::from($row['billing_period']),
            'subtotal' => $row['subtotal'],
            'vat_amount' => $row['vat_amount'],
            'total' => $row['total'],
        ], $rows));
    }

    public function groupSuffix(QuoteBillingPeriodEnum $period): string
    {
        $suffix = $period->shortSuffix();

        return $suffix !== null ? ' / '.$suffix : '';
    }

    public function groupTotal(Quote $quote, QuoteBillingPeriodEnum $period): ?string
    {
        foreach ($this->groups($quote) as $group) {
            if ($group['period'] === $period) {
                return $this->money($quote, $group['total']);
            }
        }

        return null;
    }

    /** A quote with one-off items only has nothing to split by period, so its total is just "Spolu". */
    public function isOneOffOnly(Quote $quote): bool
    {
        $groups = $this->groups($quote);

        return count($groups) === 1 && $groups[0]['period'] === QuoteBillingPeriodEnum::OneOff;
    }

    public function groupLabel(Quote $quote, QuoteBillingPeriodEnum $period): string
    {
        return $this->isOneOffOnly($quote) ? __('app.quote_total') : $period->label();
    }

    public function totalsLine(Quote $quote): string
    {
        if ($this->isOneOffOnly($quote)) {
            return $this->money($quote, $this->groups($quote)[0]['total']);
        }

        return implode(' + ', array_map(
            fn (array $group): string => $this->money($quote, $group['total']).' '.Str::lower($group['period']->label()),
            $this->groups($quote),
        ));
    }

    /** @return list<array{label: string, value: string, bold: bool}> */
    public function totalRows(Quote $quote): array
    {
        $rows = [];

        foreach ($this->groups($quote) as $group) {
            $suffix = $this->groupSuffix($group['period']);
            $label = $this->groupLabel($quote, $group['period']);

            if ($quote->is_vat_payer) {
                $rows[] = ['label' => $label.' · '.__('app.document_quote_subtotal'), 'value' => $this->money($quote, $group['subtotal']), 'bold' => false];
                $rows[] = ['label' => $label.' · '.__('app.document_quote_vat'), 'value' => $this->money($quote, $group['vat_amount']), 'bold' => false];
            }

            $rows[] = ['label' => $label, 'value' => $this->money($quote, $group['total']).$suffix, 'bold' => true];
        }

        return $rows;
    }

    public function datedLine(Quote $quote, Tenant $tenant): string
    {
        $place = filled($tenant->signing_place) ? $tenant->signing_place : $tenant->city;
        $date = format_date($quote->issue_date);

        return filled($place)
            ? $place.', '.__('app.quote_pdf_dated').' '.$date
            : Str::ucfirst(__('app.quote_pdf_dated')).' '.$date;
    }

    /** @return array{provider: array{label: string, name: ?string, lines: list<string>}, buyer: array{label: string, name: ?string, lines: list<string>}, dates: list<array{label: string, value: string}>} */
    public function partiesData(Quote $quote, Tenant $tenant): array
    {
        $party = CustomerParty::forQuote($quote);

        return [
            'provider' => [
                'label' => __('app.quote_pdf_provider'),
                'name' => $tenant->name,
                'lines' => $this->lines([
                    $tenant->address_line,
                    $this->locality($tenant->postal_code, $tenant->city),
                    $this->labelled('app.quote_pdf_ico', $tenant->ico),
                    $this->labelled('app.quote_pdf_dic', $tenant->dic),
                ]),
            ],
            'buyer' => [
                'label' => __('app.quote_pdf_orderer'),
                'name' => $party->name,
                'lines' => $this->lines([
                    filled($party->representedBy) ? __('app.quote_pdf_represented_by').':' : null,
                    $party->representedBy,
                    $party->street,
                    $this->locality($party->postalCode, $party->city),
                    $this->labelled('app.quote_pdf_ico', $party->ico),
                    $this->labelled('app.quote_pdf_dic', $party->dic),
                ]),
            ],
            'dates' => [
                ['label' => __('app.quote_pdf_issue_date'), 'value' => format_date($quote->issue_date)],
                ['label' => __('app.quote_pdf_valid_until_long'), 'value' => format_date($quote->valid_until)],
            ],
        ];
    }

    /** @return array{label: string, text: string}|null */
    public function noteData(Quote $quote): ?array
    {
        return filled($quote->note) ? ['label' => __('app.quote_pdf_note'), 'text' => (string) $quote->note] : null;
    }

    /** @return array{headers: array<string, string>, rows: list<array{description: string, note: ?string, quantity: string, unit_price: string, amount: string, suffix: ?string}>} */
    public function itemsData(Quote $quote): array
    {
        $rows = [];

        foreach ($quote->items as $item) {
            $rows[] = [
                'description' => $item->description,
                'note' => $item->note,
                'quantity' => $this->quantity($item),
                'unit_price' => $this->unitPrice($quote, $item).$this->discount($item),
                'amount' => $this->lineAmount($quote, $item),
                'suffix' => $this->lineSuffix($item),
            ];
        }

        return [
            'headers' => [
                'service' => __('app.quote_pdf_col_service'),
                'quantity' => __('app.quote_pdf_item_quantity'),
                'unit_price' => __('app.quote_pdf_col_unit_price'),
                'total' => __('app.quote_pdf_col_line_total'),
            ],
            'rows' => $rows,
        ];
    }

    /** @return array{included: array{label: string, lines: list<string>}, groups: list<array{label: string, net: ?string, vat: ?string, total: string, suffix: ?string}>, labels: array{excl_vat: string, vat: string}, clause: ?string}|null */
    public function totalsData(Quote $quote): ?array
    {
        $groups = [];

        if ($quote->show_totals) {
            foreach ($this->groups($quote) as $group) {
                $groups[] = [
                    'label' => $this->groupLabel($quote, $group['period']).($quote->is_vat_payer ? ' '.__('app.quote_pdf_incl_vat') : ''),
                    'net' => $quote->is_vat_payer ? $this->money($quote, $group['subtotal']) : null,
                    'vat' => $quote->is_vat_payer ? $this->money($quote, $group['vat_amount']) : null,
                    'total' => $this->money($quote, $group['total']),
                    'suffix' => $group['period']->shortSuffix(),
                ];
            }
        }

        $lines = $quote->includedLines();
        $clause = ! $quote->is_vat_payer && $quote->show_vat_note ? (string) __('app.quote_pdf_non_vat_clause') : null;

        if ($groups === [] && $lines === [] && $clause === null) {
            return null;
        }

        return [
            'included' => ['label' => (string) __('app.quote_pdf_included'), 'lines' => $lines],
            'groups' => $groups,
            'labels' => ['excl_vat' => (string) __('app.quote_pdf_excl_vat'), 'vat' => (string) __('app.document_quote_vat')],
            'clause' => $clause,
        ];
    }

    /**
     * @param  list<string|null>  $texts
     * @return list<string>
     */
    private function lines(array $texts): array
    {
        return array_values(array_filter($texts, static fn (?string $text): bool => $text !== null && trim($text) !== ''));
    }

    private function locality(?string $postalCode, ?string $city): ?string
    {
        $text = implode(' ', array_filter([$postalCode, $city]));

        return $text === '' ? null : $text;
    }

    private function labelled(string $key, ?string $value): ?string
    {
        return filled($value) ? __($key).': '.$value : null;
    }
}
