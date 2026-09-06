<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import RecurringInvoiceForm from '@/Components/RecurringInvoices/RecurringInvoiceForm.vue';
import type { Breadcrumb } from '@/types';

defineProps<{
    context: App.Data.Invoices.InvoiceFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('recurring_invoices'), url: '/recurring-invoices' },
    { label: t('recurring_invoice_add') },
];
</script>

<template>
    <AppLayout>
        <Header :title="t('recurring_invoice_add')" :breadcrumbs="breadcrumbs" />

        <RecurringInvoiceForm :context="context" />
    </AppLayout>
</template>
