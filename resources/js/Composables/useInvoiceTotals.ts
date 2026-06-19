import { computed, type Ref } from 'vue';

interface ItemRow {
    quantity: number;
    unit_price: number;
    discount_percent: number;
    vat_rate: number;
}

interface VatBreakdownEntry {
    rate: number;
    base: number;
    vat: number;
    total: number;
}

function roundTo2(n: number): number {
    return Math.round(n * 100) / 100;
}

export function useInvoiceTotals(
    items: Ref<ItemRow[]>,
    isVatPayer: Ref<boolean>,
    deposit: Ref<number>,
) {
    const lineCalcs = computed(() =>
        items.value.map((row) => {
            const base = roundTo2(row.quantity * row.unit_price * (1 - row.discount_percent / 100));
            const rate = isVatPayer.value ? row.vat_rate : 0;
            const vat = roundTo2(base * rate / 100);
            return { base, vat, rate };
        }),
    );

    const subtotal = computed<number>(() =>
        roundTo2(lineCalcs.value.reduce((sum, l) => sum + l.base, 0)),
    );

    const vatAmount = computed<number>(() =>
        roundTo2(lineCalcs.value.reduce((sum, l) => sum + l.vat, 0)),
    );

    const total = computed<number>(() => roundTo2(subtotal.value + vatAmount.value));

    const balanceDue = computed<number>(() => roundTo2(total.value - deposit.value));

    const vatBreakdown = computed<VatBreakdownEntry[]>(() => {
        if (!isVatPayer.value) return [];
        const groups = new Map<number, { base: number; vat: number }>();
        for (const l of lineCalcs.value) {
            const prev = groups.get(l.rate) ?? { base: 0, vat: 0 };
            groups.set(l.rate, {
                base: roundTo2(prev.base + l.base),
                vat: roundTo2(prev.vat + l.vat),
            });
        }
        return Array.from(groups.entries())
            .sort(([a], [b]) => b - a)
            .map(([rate, { base, vat }]) => ({
                rate,
                base,
                vat,
                total: roundTo2(base + vat),
            }));
    });

    return { subtotal, vatAmount, total, balanceDue, vatBreakdown };
}
