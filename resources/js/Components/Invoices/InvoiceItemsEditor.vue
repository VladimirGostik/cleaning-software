<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { PlusIcon } from '@heroicons/vue/24/outline';

import { useFormContext } from '@/Components/Forms/useFormContext';
import { callValidate } from '@/Components/Forms/useFieldError';
import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
import InvoiceItemRow from './InvoiceItemRow.vue';

export type ItemRow = {
    description: string;
    quantity: number;
    unit: string | null;
    unit_price: number;
    discount_percent: number;
    vat_rate: number;
} & Record<string, unknown>;

const props = defineProps<{
    field: string;
    isVatPayer: boolean;
    vatRateOptions: readonly number[];
    currency: App.Enums.CurrencyEnum;
    blankRow: () => ItemRow;
}>();

defineSlots<{
    'row-extra'?: (props: {
        row: ItemRow;
        index: number;
        setField: (index: number, key: string, value: unknown) => void;
        errors: Record<string, string | undefined>;
    }) => unknown;
}>();

const { t } = useI18n();
const form = useFormContext();

if (import.meta.env.DEV && !form) {
    console.warn(
        `[InvoiceItemsEditor] field="${props.field}" is set but no <FormProvider> was found in the component tree.`,
    );
}

const rows = computed<ItemRow[]>(() => {
    if (!form) return [];
    return (form as Record<string, unknown>)[props.field] as ItemRow[];
});

const errors = computed(() => (form ? (form.errors as Record<string, string | undefined>) : {}));

const rowIds = new WeakMap<object, number>();
let rowIdCounter = 0;

function rowKey(row: ItemRow): number {
    let id = rowIds.get(row);
    if (id === undefined) {
        id = ++rowIdCounter;
        rowIds.set(row, id);
    }
    return id;
}

const { lines } = useInvoiceTotals(
    rows,
    () => props.isVatPayer,
    () => 0,
    () => 'none',
);

const vatRateSelectOptions = computed(() =>
    props.vatRateOptions.map((rate) => ({ value: String(rate), label: `${rate} %` })),
);

// 2xl breakpoint (not md) + these exact column widths: tightest budget that still fits full untruncated header labels/unit placeholder in all 3 locales and keeps description >=200px at 1536px (measured against a real render).
const gridClass = computed<string>(() =>
    props.isVatPayer
        ? '2xl:grid-cols-[minmax(10rem,1fr)_5rem_7rem_6rem_5rem_4.5rem_5rem_2.25rem]'
        : '2xl:grid-cols-[minmax(10rem,1fr)_5rem_7rem_6rem_5rem_5rem_2.25rem]',
);

function addRow(): void {
    rows.value.push(props.blankRow());
}

function removeRow(index: number): void {
    rows.value.splice(index, 1);
}

function setField(index: number, key: keyof ItemRow, value: unknown): void {
    const row = rows.value[index];
    if (!row) return;
    row[key] = value;
    callValidate(form, `${props.field}.${index}.${String(key)}`);
}
</script>

<template>
    <div class="space-y-3">
        <div
            aria-hidden="true"
            class="hidden items-end gap-x-2 pb-1 text-xs font-medium uppercase tracking-wide text-base-content/60 2xl:grid"
            :class="gridClass"
        >
            <span>{{ t('invoice_pdf_item_description') }}</span>
            <span>{{ t('invoice_pdf_item_quantity') }}</span>
            <span>{{ t('invoice_pdf_item_unit') }}</span>
            <span>{{ t('invoice_pdf_item_unit_price') }}</span>
            <span>{{ t('invoice_pdf_discount') }}</span>
            <span v-if="isVatPayer">{{ t('invoice_pdf_vat_rate') }}</span>
            <span class="text-right">{{ t('invoice_item_line_total') }}</span>
            <span />
        </div>

        <div class="space-y-3 2xl:space-y-0 2xl:divide-y 2xl:divide-base-200">
            <InvoiceItemRow
                v-for="(row, index) in rows"
                :key="rowKey(row)"
                :row="row"
                :index="index"
                :field="field"
                :grid-class="gridClass"
                :is-vat-payer="isVatPayer"
                :vat-rate-options="vatRateSelectOptions"
                :currency="currency"
                :line="lines[index]"
                :errors="errors"
                @set-field="setField"
                @remove="removeRow(index)"
            >
                <template v-if="$slots['row-extra']" #row-extra="slotProps">
                    <slot name="row-extra" v-bind="slotProps" />
                </template>
            </InvoiceItemRow>
        </div>

        <p v-if="errors[field]" class="text-error text-sm">{{ errors[field] }}</p>
        <p v-if="rows.length === 0" class="text-sm text-base-content/60">{{ t('invoice_items_empty') }}</p>

        <button type="button" class="btn btn-ghost btn-sm" @click="addRow">
            <PlusIcon class="size-4" />
            {{ t('invoice_item_add') }}
        </button>
    </div>
</template>
