<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoundingModeEnum;

/**
 * SK VAT line math + per-rate recapitulation, shared by invoices and quotes
 * (`InvoiceService` / `QuoteService`). Byte-identical business knowledge, only
 * the rounding mode differs between the two documents.
 */
final readonly class DocumentTotalsCalculator
{
    /**
     * @return array{line_base: float, line_vat: float, line_total: float}
     */
    public function line(float $quantity, float $unitPrice, float $discountPercent, float $vatRate, bool $isVatPayer): array
    {
        $rate = $isVatPayer ? $vatRate : 0.0;
        $base = round($quantity * $unitPrice * (1 - $discountPercent / 100), 2);
        $vat = round($base * $rate / 100, 2);

        return [
            'line_base' => $base,
            'line_vat' => $vat,
            'line_total' => round($base + $vat, 2),
        ];
    }

    /**
     * @param  iterable<array{vat_rate: float|string, line_base: float|string, line_vat: float|string, line_total: float|string}>  $lines
     * @return array{subtotal: float, vat_amount: float, total: float, rounding_amount: float, vat_breakdown: list<array{rate: float, base: float, vat: float, total: float}>|null}
     */
    public function totals(iterable $lines, bool $isVatPayer, RoundingModeEnum $roundingMode = RoundingModeEnum::None): array
    {
        $subtotal = 0.0;
        $vatAmount = 0.0;
        $groups = [];

        foreach ($lines as $line) {
            $lineBase = (float) $line['line_base'];
            $lineVat = (float) $line['line_vat'];
            $lineTotal = (float) $line['line_total'];
            $rate = (float) $line['vat_rate'];

            $subtotal += $lineBase;
            $vatAmount += $lineVat;

            $key = (string) $rate;
            if (! isset($groups[$key])) {
                $groups[$key] = ['rate' => $rate, 'base' => 0.0, 'vat' => 0.0, 'total' => 0.0];
            }

            $groups[$key]['base'] = round($groups[$key]['base'] + $lineBase, 2);
            $groups[$key]['vat'] = round($groups[$key]['vat'] + $lineVat, 2);
            $groups[$key]['total'] = round($groups[$key]['total'] + $lineTotal, 2);
        }

        $subtotal = round($subtotal, 2);
        $vatAmount = round($vatAmount, 2);
        $totalPre = round($subtotal + $vatAmount, 2);

        $total = round($roundingMode->round($totalPre), 2);
        $roundingAmount = round($total - $totalPre, 2);

        $vatBreakdown = $isVatPayer ? array_values($groups) : [];
        usort($vatBreakdown, fn (array $a, array $b) => $b['rate'] <=> $a['rate']);

        return [
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $total,
            'rounding_amount' => $roundingAmount,
            'vat_breakdown' => $vatBreakdown ?: null,
        ];
    }
}
