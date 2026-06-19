<script setup lang="ts">
    import { computed, toRef } from 'vue';
    import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
    import InvoiceVatRecap from './InvoiceVatRecap.vue';

    interface VatRateOption {
        value: number;
        label: string;
    }

    interface ItemRow {
        id?: string | null;
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

    const props = withDefaults(
        defineProps<{
            modelValue: ItemRow[];
            isVatPayer: boolean;
            vatRateOptions?: VatRateOption[];
            defaultVatRate?: number;
            errors?: Record<string, string>;
        }>(),
        {
            vatRateOptions: () => [],
            defaultVatRate: 0,
            errors: () => ({}),
        },
    );

    const emit = defineEmits<{
        'update:modelValue': [rows: ItemRow[]];
    }>();

    const { t } = useTranslate();

    const itemsRef = computed(() => props.modelValue);
    const isVatPayerRef = toRef(props, 'isVatPayer');
    const zeroDeposit = computed<number>(() => 0);

    const { subtotal, total, vatBreakdown } = useInvoiceTotals(
        itemsRef,
        isVatPayerRef,
        zeroDeposit,
    );

    function rowBase(row: ItemRow): number {
        return Math.round(row.quantity * row.unit_price * (1 - row.discount_percent / 100) * 100) / 100;
    }

    function removeRowLabel(index: number): string {
        return t('invoices.items.remove_row').replace('{index}', String(index + 1));
    }

    function addRow() {
        emit('update:modelValue', [
            ...props.modelValue,
            {
                id: null,
                description: '',
                quantity: 1,
                unit: null,
                unit_price: 0,
                discount_percent: 0,
                vat_rate: props.defaultVatRate,
                line_base: null,
                line_vat: null,
                line_total: null,
            },
        ]);
    }

    function removeRow(index: number) {
        const updated = props.modelValue.filter((_, i) => i !== index);
        emit('update:modelValue', updated);
    }

    function updateRow(index: number, field: keyof ItemRow, value: string | number | null) {
        const updated = props.modelValue.map((row, i) => {
            if (i !== index) return row;
            return { ...row, [field]: value };
        });
        emit('update:modelValue', updated);
    }

    function formatCurrency(value: number): string {
        return value.toFixed(2);
    }
</script>

<template>
    <div class="space-y-2">
        <div class="overflow-x-auto">
            <table class="table table-sm w-full">
                <thead>
                    <tr>
                        <th class="w-[28%]">{{ t('invoices.items.description') }}</th>
                        <th class="w-[7%] text-right">{{ t('invoices.items.quantity') }}</th>
                        <th class="w-[8%]">{{ t('invoices.items.unit') }}</th>
                        <th class="w-[12%] text-right">{{ t('invoices.items.unit_price') }}</th>
                        <th class="w-[8%] text-right">{{ t('invoices.items.discount') }}</th>
                        <th v-if="isVatPayer" class="w-[10%]">{{ t('invoices.items.vat_rate') }}</th>
                        <th class="w-[13%] text-right">{{ t('invoices.items.total') }}</th>
                        <th class="w-[6%]" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in modelValue" :key="index">
                        <td>
                            <input
                                type="text"
                                :value="row.description"
                                :placeholder="t('invoices.items.description_placeholder')"
                                class="input input-sm w-full"
                                :class="{ 'input-error': errors[`items.${index}.description`] }"
                                :aria-label="t('invoices.items.description')"
                                @input="updateRow(index, 'description', ($event.target as HTMLInputElement).value)"
                            />
                            <p v-if="errors[`items.${index}.description`]" class="text-error text-xs mt-0.5">
                                {{ errors[`items.${index}.description`] }}
                            </p>
                        </td>
                        <td>
                            <input
                                type="number"
                                :value="row.quantity"
                                min="0"
                                step="0.01"
                                class="input input-sm w-full font-mono text-right"
                                :aria-label="t('invoices.items.quantity')"
                                @input="updateRow(index, 'quantity', parseFloat(($event.target as HTMLInputElement).value) || 0)"
                            />
                        </td>
                        <td>
                            <input
                                type="text"
                                :value="row.unit ?? ''"
                                :placeholder="t('invoices.items.unit_placeholder')"
                                class="input input-sm w-full"
                                :aria-label="t('invoices.items.unit')"
                                @input="updateRow(index, 'unit', ($event.target as HTMLInputElement).value || null)"
                            />
                        </td>
                        <td>
                            <input
                                type="number"
                                :value="row.unit_price"
                                min="0"
                                step="0.01"
                                class="input input-sm w-full font-mono text-right"
                                :aria-label="t('invoices.items.unit_price')"
                                @input="updateRow(index, 'unit_price', parseFloat(($event.target as HTMLInputElement).value) || 0)"
                            />
                        </td>
                        <td>
                            <input
                                type="number"
                                :value="row.discount_percent"
                                min="0"
                                max="100"
                                step="0.01"
                                class="input input-sm w-full font-mono text-right"
                                :aria-label="t('invoices.items.discount')"
                                @input="updateRow(index, 'discount_percent', parseFloat(($event.target as HTMLInputElement).value) || 0)"
                            />
                        </td>
                        <td v-if="isVatPayer">
                            <select
                                :value="row.vat_rate"
                                class="select select-sm w-full"
                                :aria-label="t('invoices.items.vat_rate')"
                                @change="updateRow(index, 'vat_rate', parseFloat(($event.target as HTMLSelectElement).value) || 0)"
                            >
                                <option
                                    v-for="opt in vatRateOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </td>
                        <td class="text-right font-mono font-medium">
                            {{ formatCurrency(rowBase(row)) }}
                        </td>
                        <td>
                            <button
                                type="button"
                                class="btn btn-ghost btn-xs text-error"
                                :aria-label="removeRowLabel(index)"
                                @click="removeRow(index)"
                            >
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="errors['items']" class="text-error text-sm">{{ errors['items'] }}</p>

        <button type="button" class="btn btn-ghost btn-sm" @click="addRow">
            <PlusIcon class="w-4 h-4" />
            {{ t('invoices.items.add_row') }}
        </button>

        <!-- Totals footer -->
        <div class="flex justify-end">
            <dl class="space-y-1 text-sm min-w-[260px]">
                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('invoices.items.subtotal') }}</dt>
                    <dd class="font-mono font-medium">{{ formatCurrency(subtotal) }}</dd>
                </div>
                <div v-if="isVatPayer" class="py-1">
                    <InvoiceVatRecap :breakdown="vatBreakdown" />
                </div>
                <div class="flex justify-between gap-4 border-t border-base-300 pt-1 font-semibold">
                    <dt>{{ t('invoices.items.total') }}</dt>
                    <dd class="font-mono">{{ formatCurrency(total) }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
