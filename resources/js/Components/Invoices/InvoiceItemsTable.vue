<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

export interface InvoiceItemsTableRow {
    description: string;
    quantity: number;
    unit: string | null;
    unit_price: number;
    discount_percent: number;
    vat_rate: number;
    line_base?: number | null;
    line_vat?: number | null;
    line_total?: number | null;
}

const props = defineProps<{
    items: readonly InvoiceItemsTableRow[];
    currency: App.Enums.CurrencyEnum;
    isVatPayer: boolean;
}>();

const { t } = useI18n();
const { money } = useMoneyFormat();

const { lines } = useInvoiceTotals(
    () => props.items,
    () => props.isVatPayer,
    () => 0,
    () => 'none',
);

const hasDiscount = computed(() => props.items.some((item) => item.discount_percent > 0));

function lineTotal(index: number, row: InvoiceItemsTableRow): number {
    return row.line_total ?? lines.value[index]?.total ?? 0;
}
</script>

<template>
    <table class="table table-sm">
        <thead>
            <tr>
                <th>{{ t('invoice_pdf_item_description') }}</th>
                <th class="text-right">{{ t('invoice_pdf_item_quantity') }}</th>
                <th>{{ t('invoice_pdf_item_unit') }}</th>
                <th class="text-right">{{ t('invoice_pdf_item_unit_price') }}</th>
                <th v-if="hasDiscount" class="text-right">{{ t('invoice_pdf_discount') }}</th>
                <th v-if="props.isVatPayer" class="text-right">{{ t('invoice_pdf_vat_rate') }}</th>
                <th class="text-right">{{ t('invoice_pdf_item_total') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="(item, index) in props.items" :key="index">
                <td>{{ item.description }}</td>
                <td class="text-right">{{ item.quantity }}</td>
                <td>{{ item.unit ?? t('empty_dash') }}</td>
                <td class="text-right">{{ money(item.unit_price, props.currency) }}</td>
                <td v-if="hasDiscount" class="text-right">
                    {{ item.discount_percent > 0 ? `${item.discount_percent} %` : t('empty_dash') }}
                </td>
                <td v-if="props.isVatPayer" class="text-right">{{ item.vat_rate }} %</td>
                <td class="text-right font-mono">{{ money(lineTotal(index, item), props.currency) }}</td>
            </tr>
        </tbody>
    </table>
</template>
