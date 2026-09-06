<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import InvoiceForm from '@/Components/Invoices/InvoiceForm.vue';
import type { Breadcrumb } from '@/types';

defineProps<{
    context: App.Data.Invoices.InvoiceFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('invoices'), url: '/invoices' },
    { label: t('invoice_add') },
];
</script>

<template>
    <AppLayout>
        <Header :title="t('invoice_add')" :breadcrumbs="breadcrumbs" />

        <InvoiceForm :context="context" />
    </AppLayout>
</template>
