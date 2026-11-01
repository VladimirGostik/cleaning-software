<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { taskFrequencyKey } from '@/utils/enums';
import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

export interface InvoiceItemsTableRow {
    description: string;
    quantity: number;
    unit: string | null;
    unit_price: number;
    discount_percent: number;
    vat_rate: number;
    frequency?: App.Enums.TaskFrequencyEnum | null;
    note?: string | null;
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
    <div class="-mx-3 overflow-x-auto px-3">
        <table class="table table-sm w-full [&_td]:px-2 [&_th]:px-2">
            <thead>
                <tr>
                    <th>{{ t('invoice_pdf_item_description') }}</th>
                    <th class="text-right whitespace-nowrap">{{ t('invoice_pdf_item_quantity') }}</th>
                    <th class="text-right whitespace-nowrap">{{ t('invoice_pdf_item_unit_price') }}</th>
                    <th v-if="hasDiscount" class="text-right whitespace-nowrap">{{ t('invoice_pdf_discount') }}</th>
                    <th v-if="props.isVatPayer" class="text-right whitespace-nowrap">
                        {{ t('invoice_pdf_vat_rate') }}
                    </th>
                    <th class="text-right whitespace-nowrap">{{ t('invoice_pdf_item_total') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, index) in props.items" :key="index">
                    <td class="min-w-32">
                        {{ item.description }}
                        <p v-if="item.frequency" class="text-xs text-base-content/60">
                            {{ t(taskFrequencyKey(item.frequency)) }}
                        </p>
                        <p v-if="item.note" class="text-xs text-base-content/60">{{ item.note }}</p>
                    </td>
                    <td class="text-right tabular-nums whitespace-nowrap">
                        {{ item.unit ? `${item.quantity} ${item.unit}` : item.quantity }}
                    </td>
                    <td class="text-right tabular-nums whitespace-nowrap">
                        {{ money(item.unit_price, props.currency) }}
                    </td>
                    <td v-if="hasDiscount" class="text-right tabular-nums whitespace-nowrap">
                        {{ item.discount_percent > 0 ? `${item.discount_percent} %` : t('empty_dash') }}
                    </td>
                    <td v-if="props.isVatPayer" class="text-right tabular-nums whitespace-nowrap">
                        {{ item.vat_rate }} %
                    </td>
                    <td class="text-right tabular-nums whitespace-nowrap">
                        {{ money(lineTotal(index, item), props.currency) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
