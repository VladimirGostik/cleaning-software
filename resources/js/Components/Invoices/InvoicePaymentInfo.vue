<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps<{
    invoice: App.Data.Invoices.InvoiceDetailData;
}>();

const { t } = useI18n();
const { money } = useMoneyFormat();

const visible = computed(() => !!props.invoice.supplier.iban || !!props.invoice.variable_symbol);
</script>

<template>
    <div v-if="visible" class="rounded-lg bg-base-200/50 p-4 text-sm">
        <p v-if="props.invoice.supplier.iban">
            <span class="text-base-content/60">{{ t('invoice_pdf_iban') }}:</span>
            <span class="ml-1 font-mono">{{ props.invoice.supplier.iban }}</span>
        </p>
        <p v-if="props.invoice.supplier.swift">
            <span class="text-base-content/60">{{ t('invoice_pdf_swift') }}:</span>
            <span class="ml-1 font-mono">{{ props.invoice.supplier.swift }}</span>
        </p>
        <p v-if="props.invoice.variable_symbol">
            <span class="text-base-content/60">{{ t('invoice_pdf_variable_symbol') }}:</span>
            <span class="ml-1 font-mono">{{ props.invoice.variable_symbol }}</span>
        </p>
        <p class="mt-2 font-semibold">
            {{ t('invoice_pdf_balance_due') }}: {{ money(props.invoice.balance_due, props.invoice.currency) }}
        </p>
    </div>
</template>
