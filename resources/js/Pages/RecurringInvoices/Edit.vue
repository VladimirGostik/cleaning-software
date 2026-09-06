<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import RecurringInvoiceForm from '@/Components/RecurringInvoices/RecurringInvoiceForm.vue';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    recurringInvoice: App.Data.RecurringInvoices.RecurringInvoiceDetailData;
    context: App.Data.Invoices.InvoiceFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('recurring_invoices'), url: '/recurring-invoices' },
    { label: props.recurringInvoice.name, url: `/recurring-invoices/${props.recurringInvoice.id}` },
    { label: t('recurring_invoice_edit') },
]);

const isEditable = props.recurringInvoice.status === 'active' || props.recurringInvoice.status === 'paused';
</script>

<template>
    <AppLayout>
        <Header :title="t('recurring_invoice_edit')" :breadcrumbs="breadcrumbs" />

        <div v-if="!isEditable" class="alert alert-warning">
            <span>{{ t('recurring_invoice_not_editable') }}</span>
        </div>

        <RecurringInvoiceForm v-else :context="context" :recurring-invoice="recurringInvoice" />
    </AppLayout>
</template>
