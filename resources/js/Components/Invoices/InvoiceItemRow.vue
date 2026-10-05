<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { TrashIcon } from '@heroicons/vue/24/outline';

import TextInput from '@/Components/Forms/TextInput.vue';
import NumberInput from '@/Components/Forms/NumberInput.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import type { ItemRow } from './InvoiceItemsEditor.vue';
import type { LineTotals } from '@/Composables/useInvoiceTotals';

defineProps<{
    row: ItemRow;
    index: number;
    field: string;
    gridClass: string;
    isVatPayer: boolean;
    vatRateOptions: SelectOption[];
    currency: App.Enums.CurrencyEnum;
    line: LineTotals | undefined;
    errors: Record<string, string | undefined>;
}>();

const emit = defineEmits<{
    setField: [index: number, key: keyof ItemRow, value: unknown];
    remove: [];
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
const { money } = useMoneyFormat();

function setField(index: number, key: string, value: unknown): void {
    emit('setField', index, key as keyof ItemRow, value);
}
</script>

<template>
    <div
        class="grid grid-cols-2 gap-2 rounded-box bg-base-200 p-3 2xl:items-start 2xl:gap-x-2 2xl:gap-y-0 2xl:rounded-none 2xl:bg-transparent 2xl:p-0 2xl:py-2"
        :class="gridClass"
    >
        <div class="col-span-2 2xl:col-span-1">
            <TextInput
                :model-value="row.description"
                :label="t('invoice_pdf_item_description')"
                label-class="2xl:sr-only"
                required
                :placeholder="t('invoice_item_description_placeholder')"
                :error="errors[`${field}.${index}.description`]"
                @update:model-value="setField(index, 'description', $event)"
            />
        </div>

        <NumberInput
            :model-value="row.quantity"
            :label="t('invoice_pdf_item_quantity')"
            label-class="2xl:sr-only"
            :min="0"
            :step="0.01"
            :error="errors[`${field}.${index}.quantity`]"
            @update:model-value="setField(index, 'quantity', $event ?? 0)"
        />

        <TextInput
            :model-value="row.unit ?? ''"
            :label="t('invoice_pdf_item_unit')"
            label-class="2xl:sr-only"
            :placeholder="t('invoice_item_unit_placeholder')"
            :error="errors[`${field}.${index}.unit`]"
            @update:model-value="setField(index, 'unit', $event || null)"
        />

        <NumberInput
            :model-value="row.unit_price"
            :label="t('invoice_pdf_item_unit_price')"
            label-class="2xl:sr-only"
            :min="0"
            :step="0.01"
            :error="errors[`${field}.${index}.unit_price`]"
            @update:model-value="setField(index, 'unit_price', $event ?? 0)"
        />

        <NumberInput
            :model-value="row.discount_percent"
            :label="t('invoice_pdf_discount')"
            label-class="2xl:sr-only"
            :min="0"
            :max="100"
            :step="0.01"
            :error="errors[`${field}.${index}.discount_percent`]"
            @update:model-value="setField(index, 'discount_percent', $event ?? 0)"
        />

        <SelectInput
            v-if="isVatPayer"
            :model-value="String(row.vat_rate)"
            :label="t('invoice_pdf_vat_rate')"
            label-class="2xl:sr-only"
            :options="vatRateOptions"
            :error="errors[`${field}.${index}.vat_rate`]"
            @update:model-value="setField(index, 'vat_rate', parseFloat($event))"
        />

        <div class="self-center text-right 2xl:pt-1">
            <span class="text-xs text-base-content/60 2xl:hidden">{{ t('invoice_item_line_total') }}</span>
            <span class="block font-semibold tabular-nums">{{ money(line?.total ?? 0, currency) }}</span>
            <span v-if="isVatPayer" class="block text-xs text-base-content/60 tabular-nums">
                {{ money(line?.base ?? 0, currency) }} + {{ money(line?.vat ?? 0, currency) }}
            </span>
        </div>

        <button
            type="button"
            class="btn btn-ghost btn-xs btn-square justify-self-end self-center"
            :aria-label="t('invoice_item_remove', { index: index + 1 })"
            :title="t('invoice_item_remove', { index: index + 1 })"
            @click="emit('remove')"
        >
            <TrashIcon class="size-4" />
        </button>

        <div v-if="$slots['row-extra']" class="col-span-2 2xl:col-span-full">
            <slot name="row-extra" :row="row" :index="index" :set-field="setField" :errors="errors" />
        </div>
    </div>
</template>
