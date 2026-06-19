<script setup lang="ts">
    import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';

    interface VatRateOption {
        value: number;
        label: string;
    }

    interface ItemRow {
        description: string;
        quantity: number;
        unit: string | null;
        unit_price: number;
        discount_percent: number;
        vat_rate: number;
    }

    const props = withDefaults(
        defineProps<{
            modelValue: ItemRow[];
            isVatPayer?: boolean;
            vatRateOptions?: VatRateOption[];
            errors?: Record<string, string>;
        }>(),
        {
            isVatPayer: false,
            vatRateOptions: () => [],
            errors: () => ({}),
        },
    );

    const emit = defineEmits<{
        'update:modelValue': [rows: ItemRow[]];
    }>();

    const { t } = useTranslate();

    function removeRowLabel(index: number): string {
        return t('recurring_invoices.items.remove_row').replace('{index}', String(index + 1));
    }

    function addRow(): void {
        emit('update:modelValue', [
            ...props.modelValue,
            { description: '', quantity: 1, unit: null, unit_price: 0, discount_percent: 0, vat_rate: 0 },
        ]);
    }

    function removeRow(index: number): void {
        const updated = props.modelValue.filter((_, i) => i !== index);
        emit('update:modelValue', updated);
    }

    function updateRow(index: number, field: keyof ItemRow, value: string | number | null): void {
        const updated = props.modelValue.map((row, i) => {
            if (i !== index) return row;
            return { ...row, [field]: value };
        });
        emit('update:modelValue', updated);
    }
</script>

<template>
    <div class="space-y-2">
        <div class="overflow-x-auto">
            <table class="table table-sm w-full">
                <thead>
                    <tr>
                        <th class="w-[30%]">{{ t('invoices.items.description') }}</th>
                        <th class="w-[8%] text-right">{{ t('invoices.items.quantity') }}</th>
                        <th class="w-[10%]">{{ t('invoices.items.unit') }}</th>
                        <th class="w-[18%] text-right">{{ t('invoices.items.unit_price') }}</th>
                        <th class="w-[10%] text-right">{{ t('invoices.items.discount') }}</th>
                        <th v-if="isVatPayer" class="w-[14%]">{{ t('invoices.items.vat_rate') }}</th>
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
    </div>
</template>
