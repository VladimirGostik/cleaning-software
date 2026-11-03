<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\QuoteBillingPeriodEnum;
use App\Models\Quote;
use App\Models\QuoteItem;
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

    public function unitPrice(Quote $quote, QuoteItem $item): string
    {
        $text = $this->money($quote, $item->unit_price);

        if ($item->unit !== null && $item->unit !== '') {
            $text .= ' / '.$item->unit;
        } elseif ($item->billing_period->isRecurring() && (float) $item->quantity === 1.0) {
            $text .= ' / '.$item->billing_period->unitNoun();
        }

        return $text;
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

    public function totalsLine(Quote $quote): string
    {
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

            if ($quote->is_vat_payer) {
                $rows[] = ['label' => $group['period']->label().' · '.__('app.document_quote_subtotal'), 'value' => $this->money($quote, $group['subtotal']), 'bold' => false];
                $rows[] = ['label' => $group['period']->label().' · '.__('app.document_quote_vat'), 'value' => $this->money($quote, $group['vat_amount']), 'bold' => false];
            }

            $rows[] = ['label' => $group['period']->label(), 'value' => $this->money($quote, $group['total']).$suffix, 'bold' => true];
        }

        return $rows;
    }
}
