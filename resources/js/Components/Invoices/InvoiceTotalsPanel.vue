<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import InvoiceVatRecap from './InvoiceVatRecap.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import type { VatBreakdownLine } from '@/Composables/useInvoiceTotals';

const props = defineProps<{
    subtotal: number;
    vatAmount: number;
    vatBreakdown: readonly VatBreakdownLine[];
    roundingAmount: number;
    total: number;
    deposit: number;
    balanceDue: number;
    currency: App.Enums.CurrencyEnum;
    isVatPayer: boolean;
}>();

const { t } = useI18n();
const { money } = useMoneyFormat();
</script>

<template>
    <dl class="space-y-2 text-sm">
        <div class="flex justify-between">
            <dt class="text-muted">{{ t('invoice_pdf_subtotal') }}</dt>
            <dd class="tabular-nums">{{ money(props.subtotal, props.currency) }}</dd>
        </div>

        <InvoiceVatRecap v-if="props.isVatPayer" :breakdown="props.vatBreakdown" :currency="props.currency" />

        <div v-if="props.roundingAmount !== 0" class="flex justify-between">
            <dt class="text-muted">{{ t('invoice_pdf_rounding') }}</dt>
            <dd class="tabular-nums">{{ money(props.roundingAmount, props.currency) }}</dd>
        </div>

        <div class="flex justify-between pt-3 text-base font-medium">
            <dt>{{ t('invoice_pdf_total') }}</dt>
            <dd class="tabular-nums">{{ money(props.total, props.currency) }}</dd>
        </div>

        <template v-if="props.deposit > 0">
            <div class="flex justify-between">
                <dt class="text-muted">{{ t('invoice_pdf_deposit') }}</dt>
                <dd class="tabular-nums">{{ money(props.deposit, props.currency) }}</dd>
            </div>
            <div class="flex justify-between font-medium">
                <dt>{{ t('invoice_pdf_balance_due') }}</dt>
                <dd class="tabular-nums">{{ money(props.balanceDue, props.currency) }}</dd>
            </div>
        </template>

        <p v-if="!props.isVatPayer" class="text-xs text-muted">
            {{ t('invoice_pdf_non_vat_payer_clause') }}
        </p>
    </dl>
</template>
