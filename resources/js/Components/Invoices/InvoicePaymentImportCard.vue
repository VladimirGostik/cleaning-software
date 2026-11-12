<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import CheckboxInput from '@/Components/Forms/CheckboxInput.vue';
import InvoicePaymentImportMatch from '@/Components/Invoices/InvoicePaymentImportMatch.vue';
import InvoicePaymentImportStateChip from '@/Components/Invoices/InvoicePaymentImportStateChip.vue';
import { formatDate } from '@/utils/date';
import { formatMoney } from '@/utils/money';
import { canInclude, type PaymentImportRow, type RowProjection } from '@/utils/invoicePaymentImport';

defineProps<{
    row: PaymentImportRow;
    projection: RowProjection | null;
    disabled: boolean;
}>();

const emit = defineEmits<{
    include: [reference: string, value: boolean];
    assign: [reference: string];
    unassign: [reference: string];
}>();

const { t, locale } = useI18n();
</script>

<template>
    <article
        class="space-y-2 rounded-card-nested bg-base-200 p-3"
        :class="{ 'text-muted': row.state === 'already_imported' }"
    >
        <div class="flex items-start justify-between gap-2">
            <CheckboxInput
                :model-value="row.include"
                :label="`${t('invoice_payment_import_include')} ${formatDate(row.date)} ${formatMoney(row.amount, 'EUR', locale)}`"
                label-class="sr-only"
                :disabled="disabled || !canInclude(row)"
                @update:model-value="emit('include', row.reference, $event)"
            />
            <InvoicePaymentImportStateChip :state="row.state" :manual="row.manual_invoice !== null" />
        </div>
        <div class="flex items-baseline justify-between gap-2">
            <span class="text-lg font-medium tabular-nums">{{ formatMoney(row.amount, 'EUR', locale) }}</span>
            <span class="text-sm text-muted">{{ formatDate(row.date) }}</span>
        </div>
        <p class="text-sm">{{ row.payer_name ?? t('empty_dash') }}</p>
        <p v-if="row.variable_symbol" class="text-sm text-muted tabular-nums">
            {{ t('invoice_payment_import_col_vs') }}: {{ row.variable_symbol }}
        </p>
        <p v-if="row.note" class="line-clamp-2 text-sm text-muted" :title="row.note">{{ row.note }}</p>
        <InvoicePaymentImportMatch
            :row="row"
            :projection="projection"
            :disabled="disabled"
            @assign="emit('assign', row.reference)"
            @unassign="emit('unassign', row.reference)"
        />
    </article>
</template>
