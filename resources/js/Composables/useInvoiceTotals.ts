import { computed, type Ref } from 'vue';

interface ItemRow {
    quantity: number;
    unit_price: number;
}

function rowTotal(row: ItemRow): number {
    return Math.round(row.quantity * row.unit_price * 100) / 100;
}

export function useInvoiceTotals(
    items: Ref<ItemRow[]>,
    isVatPayer: Ref<boolean>,
    vatRate: Ref<string | null>,
) {
    const subtotal = computed<number>(() =>
        items.value.reduce((sum, row) => sum + rowTotal(row), 0),
    );

    const vatAmount = computed<number>(() => {
        if (!isVatPayer.value || !vatRate.value) return 0;
        const rate = parseFloat(vatRate.value);
        return isNaN(rate) ? 0 : Math.round(subtotal.value * (rate / 100) * 100) / 100;
    });

    const total = computed<number>(() =>
        Math.round((subtotal.value + vatAmount.value) * 100) / 100,
    );

    return { subtotal, vatAmount, total };
}
