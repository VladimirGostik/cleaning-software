<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import { formatMoney } from '@/utils/money';
import { PAYMENT_IMPORT_FILTERS, type PaymentImportFilter } from '@/utils/invoicePaymentImport';

defineProps<{
    totals: Record<PaymentImportFilter, { count: number; amount: string }>;
}>();

const filter = defineModel<PaymentImportFilter>('filter', { required: true });
const search = defineModel<string>('search', { required: true });

const { t, locale } = useI18n();
</script>

<template>
    <div class="space-y-3">
        <div role="tablist" class="flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="key in PAYMENT_IMPORT_FILTERS"
                :key="key"
                type="button"
                role="tab"
                class="btn btn-sm shrink-0 h-auto flex-col items-start gap-0 py-1.5 normal-case"
                :class="filter === key ? 'btn-primary' : 'btn-neutral'"
                :aria-selected="filter === key"
                @click="filter = key"
            >
                <span>{{ t(`invoice_payment_import_filter_${key}`) }} ({{ totals[key].count }})</span>
                <span class="text-xs font-normal tabular-nums">{{
                    formatMoney(totals[key].amount, 'EUR', locale)
                }}</span>
            </button>
        </div>
        <TextInput
            v-model="search"
            type="search"
            :label="t('invoice_payment_import_search')"
            label-class="sr-only"
            :placeholder="t('invoice_payment_import_search')"
        />
    </div>
</template>
