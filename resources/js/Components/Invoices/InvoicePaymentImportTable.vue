<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CheckboxInput from '@/Components/Forms/CheckboxInput.vue';
import InvoicePaymentImportCard from '@/Components/Invoices/InvoicePaymentImportCard.vue';
import InvoicePaymentImportMatch from '@/Components/Invoices/InvoicePaymentImportMatch.vue';
import InvoicePaymentImportStateChip from '@/Components/Invoices/InvoicePaymentImportStateChip.vue';
import { formatDate } from '@/utils/date';
import { formatMoney } from '@/utils/money';
import { canInclude, type PaymentImportRow, type RowProjection } from '@/utils/invoicePaymentImport';

const props = defineProps<{
    rows: PaymentImportRow[];
    projections: Map<string, RowProjection | null>;
    disabled: boolean;
}>();

const emit = defineEmits<{
    include: [reference: string, value: boolean];
    includeVisible: [value: boolean];
    assign: [reference: string];
    unassign: [reference: string];
}>();

const { t, locale } = useI18n();

const includable = computed(() => props.rows.filter(canInclude));
const allChecked = computed(() => includable.value.length > 0 && includable.value.every((row) => row.include));
</script>

<template>
    <div>
        <div class="hidden overflow-x-auto md:block">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>
                            <CheckboxInput
                                :model-value="allChecked"
                                :label="t('invoice_payment_import_include_visible')"
                                label-class="sr-only"
                                :disabled="disabled || includable.length === 0"
                                @update:model-value="emit('includeVisible', $event)"
                            />
                        </th>
                        <th>{{ t('invoice_payment_import_col_state') }}</th>
                        <th>{{ t('invoice_payment_import_col_date') }}</th>
                        <th class="text-right">{{ t('invoice_payment_import_col_amount') }}</th>
                        <th>{{ t('invoice_payment_import_col_payer') }}</th>
                        <th>{{ t('invoice_payment_import_col_invoice') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.reference"
                        class="align-top"
                        :class="{ 'text-muted': row.state === 'already_imported' }"
                    >
                        <td>
                            <CheckboxInput
                                :model-value="row.include"
                                :label="`${t('invoice_payment_import_include')} ${formatDate(row.date)} ${formatMoney(row.amount, 'EUR', locale)}`"
                                label-class="sr-only"
                                :disabled="disabled || !canInclude(row)"
                                @update:model-value="emit('include', row.reference, $event)"
                            />
                        </td>
                        <td>
                            <InvoicePaymentImportStateChip :state="row.state" :manual="row.manual_invoice !== null" />
                        </td>
                        <td class="whitespace-nowrap">{{ formatDate(row.date) }}</td>
                        <td class="whitespace-nowrap text-right tabular-nums">
                            {{ formatMoney(row.amount, 'EUR', locale) }}
                        </td>
                        <td class="max-w-64">
                            <p>{{ row.payer_name ?? t('empty_dash') }}</p>
                            <p v-if="row.variable_symbol" class="text-xs text-muted tabular-nums">
                                {{ t('invoice_payment_import_col_vs') }}: {{ row.variable_symbol }}
                            </p>
                            <p v-if="row.note" class="line-clamp-2 text-xs text-muted" :title="row.note">
                                {{ row.note }}
                            </p>
                        </td>
                        <td class="min-w-56">
                            <InvoicePaymentImportMatch
                                :row="row"
                                :projection="projections.get(row.reference) ?? null"
                                :disabled="disabled"
                                @assign="emit('assign', row.reference)"
                                @unassign="emit('unassign', row.reference)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-3 md:hidden">
            <InvoicePaymentImportCard
                v-for="row in rows"
                :key="row.reference"
                :row="row"
                :projection="projections.get(row.reference) ?? null"
                :disabled="disabled"
                @include="(reference, value) => emit('include', reference, value)"
                @assign="(reference) => emit('assign', reference)"
                @unassign="(reference) => emit('unassign', reference)"
            />
        </div>
    </div>
</template>
