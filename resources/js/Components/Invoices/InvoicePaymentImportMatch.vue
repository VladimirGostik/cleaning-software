<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { formatDate } from '@/utils/date';
import { formatMoney } from '@/utils/money';
import { canAssign, effectiveInvoice, type PaymentImportRow, type RowProjection } from '@/utils/invoicePaymentImport';

defineProps<{
    row: PaymentImportRow;
    projection: RowProjection | null;
    disabled: boolean;
}>();

const emit = defineEmits<{
    assign: [];
    unassign: [];
}>();

const { t, locale } = useI18n();
</script>

<template>
    <div class="space-y-1 text-sm">
        <template v-if="effectiveInvoice(row)">
            <div class="flex flex-wrap items-center gap-x-2">
                <a
                    :href="`/invoices/${effectiveInvoice(row)!.id}`"
                    target="_blank"
                    rel="noopener"
                    class="link link-hover font-medium tabular-nums"
                >
                    {{ effectiveInvoice(row)!.number }}
                </a>
                <span v-if="row.instalment_count" class="text-muted">
                    {{
                        t('invoice_payment_import_instalment', {
                            position: row.instalment_position,
                            count: row.instalment_count,
                        })
                    }}
                </span>
            </div>
            <p v-if="effectiveInvoice(row)!.customer_name" class="text-muted">
                {{ effectiveInvoice(row)!.customer_name }}
            </p>
            <p class="text-muted tabular-nums">
                {{ t('invoice_payment_import_balance') }}:
                {{ formatMoney(effectiveInvoice(row)!.balance_due, 'EUR', locale) }}
                <span class="whitespace-nowrap">({{ formatDate(effectiveInvoice(row)!.due_date) }})</span>
            </p>
            <p v-if="row.state === 'already_imported'">
                <a :href="`/invoices/${effectiveInvoice(row)!.id}`" target="_blank" rel="noopener" class="link">
                    {{ t('invoice_payment_import_view_existing') }}
                </a>
            </p>
            <template v-else-if="projection">
                <p v-if="projection.outcome === 'nothing'" class="text-muted">
                    {{ t('invoice_payment_import_nothing_to_record') }}
                </p>
                <p v-else class="tabular-nums">
                    {{
                        t('invoice_payment_import_will_record', {
                            amount: formatMoney(projection.amount_to_record, 'EUR', locale),
                        })
                    }}
                    <span v-if="projection.outcome === 'overpaid'" class="text-warning">
                        {{
                            t('invoice_payment_import_excess', {
                                amount: formatMoney(projection.excess, 'EUR', locale),
                            })
                        }}
                    </span>
                </p>
            </template>
        </template>
        <p v-else-if="row.state !== 'already_imported'" class="text-muted">{{ t('empty_dash') }}</p>

        <div v-if="canAssign(row)" class="flex flex-wrap gap-1 pt-1">
            <button type="button" class="btn btn-neutral btn-xs" :disabled="disabled" @click="emit('assign')">
                {{ t(effectiveInvoice(row) ? 'invoice_payment_import_change' : 'invoice_payment_import_assign') }}
            </button>
            <button
                v-if="row.manual_invoice"
                type="button"
                class="btn btn-neutral btn-xs"
                :disabled="disabled"
                @click="emit('unassign')"
            >
                {{ t('invoice_payment_import_unassign') }}
            </button>
        </div>
    </div>
</template>
