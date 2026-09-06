<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ArrowPathIcon, ClockIcon, ExclamationTriangleIcon, ReceiptPercentIcon } from '@heroicons/vue/24/outline';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps<{
    stats: App.Data.Invoices.InvoiceStatsData;
}>();

const { t } = useI18n();
const { money } = useMoneyFormat();

const cards = [
    { key: 'issued_this_month', label: 'invoice_stat_issued_this_month', icon: ReceiptPercentIcon, error: false },
    { key: 'overdue', label: 'invoice_stat_overdue', icon: ExclamationTriangleIcon, error: true },
    { key: 'pending', label: 'invoice_stat_pending', icon: ClockIcon, error: false },
    { key: 'recurring_monthly', label: 'invoice_stat_recurring_monthly', icon: ArrowPathIcon, error: false },
] as const;
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
            v-for="card in cards"
            :key="card.key"
            class="card border border-base-300 border-b-2 border-b-primary bg-base-100 shadow-sm"
        >
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-base-content/60">{{ t(card.label) }}</p>
                    <component :is="card.icon" class="size-5 text-base-content/40" />
                </div>
                <p class="text-2xl font-bold" :class="card.error ? 'text-error' : 'text-primary'">
                    {{ money(props.stats[card.key].amount, props.stats.currency) }}
                </p>
                <p class="text-xs text-base-content/50">
                    {{ t('invoice_stat_count', { count: props.stats[card.key].count }) }}
                </p>
            </div>
        </div>
    </div>
</template>
