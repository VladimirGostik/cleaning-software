<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import QuoteKindBadge from './QuoteKindBadge.vue';
import InvoiceTotalsPanel from '@/Components/Invoices/InvoiceTotalsPanel.vue';
import { formatDate } from '@/utils/date';
import type { VatBreakdownLine } from '@/Composables/useInvoiceTotals';

const props = defineProps<{
    number: string | null;
    kind: App.Enums.QuoteKindEnum;
    issueDate: string;
    validUntil: string;
    currency: App.Enums.CurrencyEnum;
    isVatPayer: boolean;
    subtotal: number;
    vatAmount: number;
    vatBreakdown: readonly VatBreakdownLine[];
    total: number;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('quote_section_summary') }}</h2>

            <div class="flex items-center justify-between">
                <span class="font-mono font-medium">{{ props.number ?? t('quote_no_number') }}</span>
                <QuoteKindBadge :kind="props.kind" />
            </div>

            <div class="grid grid-cols-2 gap-2 text-sm text-base-content/70">
                <div>
                    <p class="text-xs text-base-content/50">{{ t('quote_issue_date') }}</p>
                    <p>{{ formatDate(props.issueDate) }}</p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50">{{ t('quote_valid_until') }}</p>
                    <p>{{ formatDate(props.validUntil) }}</p>
                </div>
            </div>

            <div class="divider my-0" />

            <InvoiceTotalsPanel
                v-if="props.kind === 'itemized'"
                :subtotal="props.subtotal"
                :vat-amount="props.vatAmount"
                :vat-breakdown="props.vatBreakdown"
                :rounding-amount="0"
                :total="props.total"
                :deposit="0"
                :balance-due="props.total"
                :currency="props.currency"
                :is-vat-payer="props.isVatPayer"
            />
        </div>
    </div>
</template>
