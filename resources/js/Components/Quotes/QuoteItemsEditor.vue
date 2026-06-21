<script setup lang="ts">
    import { computed, toRef } from 'vue';
    import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
    import InvoiceVatRecap from '@/Components/Invoices/InvoiceVatRecap.vue';

    interface VatRateOption {
        value: number;
        label: string;
    }

    const props = withDefaults(
        defineProps<{
            modelValue: App.Data.Quotes.QuoteItemData[];
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
        'update:modelValue': [rows: App.Data.Quotes.QuoteItemData[]];
    }>();

    const { t } = useTranslate();

    const itemsRef = computed(() => props.modelValue);
    const isVatPayerRef = toRef(props, 'isVatPayer');
    const zeroDeposit = computed<number>(() => 0);

    const { subtotal, total, vatBreakdown } = useInvoiceTotals(itemsRef, isVatPayerRef, zeroDeposit);

    function rowBase(row: App.Data.Quotes.QuoteItemData): number {
        return Math.round(row.quantity * row.unit_price * (1 - row.discount_percent / 100) * 100) / 100;
    }

    function removeRowLabel(index: number): string {
        return t('quotes.items.remove_row').replace('{index}', String(index + 1));
    }

    function addRow() {
        emit('update:modelValue', [
            ...props.modelValue,
            {
                id: null,
                name: '',
                description: null,
                frequency: null,
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
        emit(
            'update:modelValue',
            props.modelValue.filter((_, i) => i !== index),
        );
    }

    function updateRow(
        index: number,
        field: keyof App.Data.Quotes.QuoteItemData,
        value: string | number | null,
    ) {
        emit(
            'update:modelValue',
            props.modelValue.map((row, i) => (i === index ? { ...row, [field]: value } : row)),
        );
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
                        <th class="w-[16%]">{{ t('quotes.items.name') }}</th>
                        <th class="w-[14%]">{{ t('quotes.items.description') }}</th>
                        <th class="w-[11%]">{{ t('quotes.items.frequency') }}</th>
                        <th class="w-[6%] text-right">{{ t('quotes.items.quantity') }}</th>
                        <th class="w-[6%]">{{ t('quotes.items.unit') }}</th>
                        <th class="w-[10%] text-right">{{ t('quotes.items.unit_price') }}</th>
                        <th class="w-[7%] text-right">{{ t('quotes.items.discount') }}</th>
                        <th v-if="isVatPayer" class="w-[9%]">{{ t('quotes.items.vat_rate') }}</th>
                        <th class="w-[10%] text-right">{{ t('quotes.items.total') }}</th>
                        <th class="w-[5%]" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in modelValue" :key="index">
                        <td>
                            <input
                                type="text"
                                :value="row.name"
                                :placeholder="t('quotes.items.name_placeholder')"
                                class="input input-sm w-full"
                                :class="{ 'input-error': errors[`items.${index}.name`] }"
                                :aria-label="t('quotes.items.name')"
                                @input="updateRow(index, 'name', ($event.target as HTMLInputElement).value)"
                            />
                            <p v-if="errors[`items.${index}.name`]" class="text-error text-xs mt-0.5">
                                {{ errors[`items.${index}.name`] }}
                            </p>
                        </td>
                        <td>
                            <input
                                type="text"
                                :value="row.description ?? ''"
                                class="input input-sm w-full"
                                :aria-label="t('quotes.items.description')"
                                @input="
                                    updateRow(
                                        index,
                                        'description',
                                        ($event.target as HTMLInputElement).value || null,
                                    )
                                "
                            />
                        </td>
                        <td>
                            <input
                                type="text"
                                :value="row.frequency ?? ''"
                                class="input input-sm w-full"
                                :aria-label="t('quotes.items.frequency')"
                                @input="
                                    updateRow(
                                        index,
                                        'frequency',
                                        ($event.target as HTMLInputElement).value || null,
                                    )
                                "
                            />
                        </td>
                        <td>
                            <input
                                type="number"
                                :value="row.quantity"
                                min="0"
                                step="0.01"
                                class="input input-sm w-full font-mono text-right"
                                :aria-label="t('quotes.items.quantity')"
                                @input="
                                    updateRow(
                                        index,
                                        'quantity',
                                        parseFloat(($event.target as HTMLInputElement).value) || 0,
                                    )
                                "
                            />
                        </td>
                        <td>
                            <input
                                type="text"
                                :value="row.unit ?? ''"
                                class="input input-sm w-full"
                                :aria-label="t('quotes.items.unit')"
                                @input="
                                    updateRow(
                                        index,
                                        'unit',
                                        ($event.target as HTMLInputElement).value || null,
                                    )
                                "
                            />
                        </td>
                        <td>
                            <input
                                type="number"
                                :value="row.unit_price"
                                min="0"
                                step="0.01"
                                class="input input-sm w-full font-mono text-right"
                                :aria-label="t('quotes.items.unit_price')"
                                @input="
                                    updateRow(
                                        index,
                                        'unit_price',
                                        parseFloat(($event.target as HTMLInputElement).value) || 0,
                                    )
                                "
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
                                :aria-label="t('quotes.items.discount')"
                                @input="
                                    updateRow(
                                        index,
                                        'discount_percent',
                                        parseFloat(($event.target as HTMLInputElement).value) || 0,
                                    )
                                "
                            />
                        </td>
                        <td v-if="isVatPayer">
                            <select
                                :value="row.vat_rate"
                                class="select select-sm w-full"
                                :aria-label="t('quotes.items.vat_rate')"
                                @change="
                                    updateRow(
                                        index,
                                        'vat_rate',
                                        parseFloat(($event.target as HTMLSelectElement).value) || 0,
                                    )
                                "
                            >
                                <option v-for="opt in vatRateOptions" :key="opt.value" :value="opt.value">
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
            {{ t('quotes.items.add_row') }}
        </button>

        <!-- Totals footer -->
        <div class="flex justify-end">
            <dl class="space-y-1 text-sm min-w-[260px]">
                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">{{ t('quotes.items.subtotal') }}</dt>
                    <dd class="font-mono font-medium">{{ formatCurrency(subtotal) }}</dd>
                </div>
                <div v-if="isVatPayer" class="py-1">
                    <InvoiceVatRecap :breakdown="vatBreakdown" />
                </div>
                <div class="flex justify-between gap-4 border-t border-base-300 pt-1 font-semibold">
                    <dt>{{ t('quotes.items.total') }}</dt>
                    <dd class="font-mono">{{ formatCurrency(total) }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
