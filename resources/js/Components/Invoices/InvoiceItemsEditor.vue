<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

import TextInput from '@/Components/Forms/TextInput.vue';
import NumberInput from '@/Components/Forms/NumberInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import { useFormContext } from '@/Components/Forms/useFormContext';
import { callValidate } from '@/Components/Forms/useFieldError';
import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

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

const { t } = useI18n();
const { money } = useMoneyFormat();
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
        <div v-for="(row, index) in rows" :key="rowKey(row)" class="card bg-base-200 p-3">
            <div class="mb-2 flex items-start justify-between gap-2">
                <div class="flex-1">
                    <TextInput
                        :model-value="row.description"
                        :label="t('invoice_pdf_item_description')"
                        required
                        :placeholder="t('invoice_item_description_placeholder')"
                        :error="errors[`${field}.${index}.description`]"
                        @update:model-value="setField(index, 'description', $event)"
                    />
                </div>

                <button
                    type="button"
                    class="btn btn-ghost btn-xs mt-6"
                    :aria-label="t('invoice_item_remove', { index: index + 1 })"
                    :title="t('invoice_item_remove', { index: index + 1 })"
                    @click="removeRow(index)"
                >
                    <TrashIcon class="size-4" />
                </button>
            </div>

            <div class="grid grid-cols-1 gap-2 md:grid-cols-5">
                <NumberInput
                    :model-value="row.quantity"
                    :label="t('invoice_pdf_item_quantity')"
                    :min="0"
                    :step="0.01"
                    :error="errors[`${field}.${index}.quantity`]"
                    @update:model-value="setField(index, 'quantity', $event ?? 0)"
                />

                <TextInput
                    :model-value="row.unit ?? ''"
                    :label="t('invoice_pdf_item_unit')"
                    :placeholder="t('invoice_item_unit_placeholder')"
                    :error="errors[`${field}.${index}.unit`]"
                    @update:model-value="setField(index, 'unit', $event || null)"
                />

                <NumberInput
                    :model-value="row.unit_price"
                    :label="t('invoice_pdf_item_unit_price')"
                    :min="0"
                    :step="0.01"
                    :error="errors[`${field}.${index}.unit_price`]"
                    @update:model-value="setField(index, 'unit_price', $event ?? 0)"
                />

                <NumberInput
                    :model-value="row.discount_percent"
                    :label="t('invoice_pdf_discount')"
                    :min="0"
                    :max="100"
                    :step="0.01"
                    :error="errors[`${field}.${index}.discount_percent`]"
                    @update:model-value="setField(index, 'discount_percent', $event ?? 0)"
                />

                <SelectInput
                    v-if="props.isVatPayer"
                    :model-value="String(row.vat_rate)"
                    :label="t('invoice_pdf_vat_rate')"
                    :options="vatRateSelectOptions"
                    :error="errors[`${field}.${index}.vat_rate`]"
                    @update:model-value="setField(index, 'vat_rate', parseFloat($event))"
                />
            </div>

            <div class="mt-2 flex flex-wrap gap-4 font-mono text-sm text-base-content/70">
                <span>{{ t('invoice_item_line_base') }}: {{ money(lines[index]?.base ?? 0, props.currency) }}</span>
                <span v-if="props.isVatPayer">
                    {{ t('invoice_item_line_vat') }}: {{ money(lines[index]?.vat ?? 0, props.currency) }}
                </span>
                <span>{{ t('invoice_item_line_total') }}: {{ money(lines[index]?.total ?? 0, props.currency) }}</span>
            </div>
        </div>

        <p v-if="errors[field]" class="text-error text-sm">{{ errors[field] }}</p>
        <p v-if="rows.length === 0" class="text-sm text-base-content/60">{{ t('invoice_items_empty') }}</p>

        <button type="button" class="btn btn-ghost btn-sm" @click="addRow">
            <PlusIcon class="size-4" />
            {{ t('invoice_item_add') }}
        </button>
    </div>
</template>
