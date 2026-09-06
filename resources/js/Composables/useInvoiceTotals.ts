import { computed, toValue, type ComputedRef, type MaybeRefOrGetter } from 'vue';
import { round2, roundAmount } from '@/utils/money';

export interface TotalsItem {
    quantity: number;
    unit_price: number;
    discount_percent: number;
    vat_rate: number;
}

export interface LineTotals {
    base: number;
    vat: number;
    total: number;
    rate: number;
}

export interface VatBreakdownLine {
    rate: number;
    base: number;
    vat: number;
    total: number;
}

export interface UseInvoiceTotalsReturn {
    lines: ComputedRef<LineTotals[]>;
    subtotal: ComputedRef<number>;
    vatAmount: ComputedRef<number>;
    totalBeforeRounding: ComputedRef<number>;
    roundingAmount: ComputedRef<number>;
    total: ComputedRef<number>;
    balanceDue: ComputedRef<number>;
    vatBreakdown: ComputedRef<VatBreakdownLine[]>;
}

/**
 * Mirrors InvoiceService::computeTotals — keep the math identical so the FE preview
 * matches what BE persists (incl. rounding mode behaviour).
 */
export function useInvoiceTotals(
    items: MaybeRefOrGetter<readonly TotalsItem[]>,
    isVatPayer: MaybeRefOrGetter<boolean>,
    deposit: MaybeRefOrGetter<number>,
    roundingMode: MaybeRefOrGetter<App.Enums.RoundingModeEnum>,
): UseInvoiceTotalsReturn {
    const lines = computed<LineTotals[]>(() =>
        toValue(items).map((item) => {
            const rate = toValue(isVatPayer) ? item.vat_rate : 0;
            const base = round2(item.quantity * item.unit_price * (1 - item.discount_percent / 100));
            const vat = round2((base * rate) / 100);
            return { base, vat, total: round2(base + vat), rate };
        }),
    );

    const subtotal = computed(() => round2(lines.value.reduce((sum, line) => sum + line.base, 0)));
    const vatAmount = computed(() => round2(lines.value.reduce((sum, line) => sum + line.vat, 0)));
    const totalBeforeRounding = computed(() => round2(subtotal.value + vatAmount.value));
    const total = computed(() => round2(roundAmount(totalBeforeRounding.value, toValue(roundingMode))));
    const roundingAmount = computed(() => round2(total.value - totalBeforeRounding.value));
    const balanceDue = computed(() => round2(total.value - toValue(deposit)));

    const vatBreakdown = computed<VatBreakdownLine[]>(() => {
        if (!toValue(isVatPayer)) return [];

        const groups = new Map<number, VatBreakdownLine>();
        for (const line of lines.value) {
            const existing = groups.get(line.rate);
            if (existing) {
                existing.base = round2(existing.base + line.base);
                existing.vat = round2(existing.vat + line.vat);
                existing.total = round2(existing.total + line.total);
            } else {
                groups.set(line.rate, { rate: line.rate, base: line.base, vat: line.vat, total: line.total });
            }
        }

        return Array.from(groups.values()).sort((a, b) => b.rate - a.rate);
    });

    return { lines, subtotal, vatAmount, totalBeforeRounding, roundingAmount, total, balanceDue, vatBreakdown };
}
