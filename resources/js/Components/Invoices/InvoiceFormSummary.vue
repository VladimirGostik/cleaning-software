<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import InvoiceTypeBadge from './InvoiceTypeBadge.vue';
import InvoiceTotalsPanel from './InvoiceTotalsPanel.vue';
import { formatDate } from '@/utils/date';
import type { VatBreakdownLine } from '@/Composables/useInvoiceTotals';

const props = defineProps<{
    number: string | null;
    type: App.Enums.InvoiceTypeEnum;
    issueDate: string;
    dueDate: string;
    currency: App.Enums.CurrencyEnum;
    isVatPayer: boolean;
    subtotal: number;
    vatAmount: number;
    vatBreakdown: readonly VatBreakdownLine[];
    roundingAmount: number;
    total: number;
    deposit: number;
    balanceDue: number;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('invoice_section_summary') }}</h2>

            <div class="flex items-center justify-between">
                <span class="font-mono font-medium">{{ props.number ?? t('invoice_draft_number') }}</span>
                <InvoiceTypeBadge :type="props.type" />
            </div>

            <div class="grid grid-cols-2 gap-2 text-sm text-base-content/70">
                <div>
                    <p class="text-xs text-base-content/50">{{ t('invoice_pdf_issue_date') }}</p>
                    <p>{{ formatDate(props.issueDate) }}</p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50">{{ t('invoice_pdf_due_date') }}</p>
                    <p>{{ formatDate(props.dueDate) }}</p>
                </div>
            </div>

            <div class="divider my-0" />

            <InvoiceTotalsPanel
                :subtotal="props.subtotal"
                :vat-amount="props.vatAmount"
                :vat-breakdown="props.vatBreakdown"
                :rounding-amount="props.roundingAmount"
                :total="props.total"
                :deposit="props.deposit"
                :balance-due="props.balanceDue"
                :currency="props.currency"
                :is-vat-payer="props.isVatPayer"
            />
        </div>
    </div>
</template>
