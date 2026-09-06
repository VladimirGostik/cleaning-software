<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { formatDate } from '@/utils/date';
import { paymentTypeKey } from '@/utils/enums';

const props = defineProps<{
    invoice: App.Data.Invoices.InvoiceDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
        <div>
            <p class="text-xs text-base-content/50">{{ t('invoice_pdf_issue_date') }}</p>
            <p>{{ formatDate(props.invoice.issue_date) }}</p>
        </div>
        <div>
            <p class="text-xs text-base-content/50">{{ t('invoice_pdf_delivery_date') }}</p>
            <p>{{ formatDate(props.invoice.delivery_date) }}</p>
        </div>
        <div>
            <p class="text-xs text-base-content/50">{{ t('invoice_pdf_due_date') }}</p>
            <p :class="{ 'text-error': props.invoice.status === 'overdue' }">
                {{ formatDate(props.invoice.due_date) }}
            </p>
        </div>
        <div>
            <p class="text-xs text-base-content/50">{{ t('invoice_pdf_payment_type') }}</p>
            <p>{{ t(paymentTypeKey(props.invoice.payment_type)) }}</p>
        </div>

        <div v-if="props.invoice.variable_symbol">
            <p class="text-xs text-base-content/50">{{ t('invoice_pdf_variable_symbol') }}</p>
            <p class="font-mono">{{ props.invoice.variable_symbol }}</p>
        </div>
        <div v-if="props.invoice.constant_symbol">
            <p class="text-xs text-base-content/50">{{ t('invoice_pdf_constant_symbol') }}</p>
            <p class="font-mono">{{ props.invoice.constant_symbol }}</p>
        </div>
        <div v-if="props.invoice.specific_symbol">
            <p class="text-xs text-base-content/50">{{ t('invoice_pdf_specific_symbol') }}</p>
            <p class="font-mono">{{ props.invoice.specific_symbol }}</p>
        </div>
        <div v-if="props.invoice.period_from">
            <p class="text-xs text-base-content/50">{{ t('invoice_pdf_period') }}</p>
            <p>{{ formatDate(props.invoice.period_from) }} – {{ formatDate(props.invoice.period_to) }}</p>
        </div>

        <div v-if="props.invoice.object_name" class="col-span-2 sm:col-span-4">
            <p class="text-xs text-base-content/50">{{ t('invoice_section_object') }}</p>
            <p>{{ props.invoice.object_name }}</p>
            <p v-if="props.invoice.object_street" class="text-base-content/70">{{ props.invoice.object_street }}</p>
            <p v-if="props.invoice.object_postal_code || props.invoice.object_city" class="text-base-content/70">
                {{ [props.invoice.object_postal_code, props.invoice.object_city].filter(Boolean).join(' ') }}
            </p>
        </div>
    </div>
</template>
