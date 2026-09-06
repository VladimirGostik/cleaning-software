<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import InvoiceForm from '@/Components/Invoices/InvoiceForm.vue';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    invoice: App.Data.Invoices.InvoiceDetailData;
    context: App.Data.Invoices.InvoiceFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('invoices'), url: '/invoices' },
    { label: props.invoice.number ?? t('invoice_draft_number'), url: `/invoices/${props.invoice.id}` },
    { label: t('invoice_edit') },
]);
</script>

<template>
    <AppLayout>
        <Header :title="t('invoice_edit')" :breadcrumbs="breadcrumbs" />

        <div v-if="invoice.status !== 'draft'" class="alert alert-warning">
            <span>{{ t('invoice_not_editable') }}</span>
        </div>

        <InvoiceForm v-else :context="context" :invoice="invoice" />
    </AppLayout>
</template>
