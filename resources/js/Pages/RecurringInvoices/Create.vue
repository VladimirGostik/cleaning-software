<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import RecurringInvoiceForm from '@/Components/RecurringInvoices/RecurringInvoiceForm.vue';
import SupplierIncompleteAlert from '@/Components/Invoices/SupplierIncompleteAlert.vue';
import InvoiceSettingsDrawer from '@/Components/Invoices/InvoiceSettingsDrawer.vue';

import { useInvoiceSettingsDrawer } from '@/Composables/useInvoiceSettingsDrawer';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    context: App.Data.Invoices.InvoiceFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('recurring_invoices'), url: '/recurring-invoices' },
    { label: t('recurring_invoice_add') },
];

const settingsDrawer = useInvoiceSettingsDrawer();

function onSettingsSaved(): void {
    settingsDrawer.close();
    router.reload({ only: ['context'] });
}
</script>

<template>
    <AppLayout>
        <Header :title="t('recurring_invoice_add')" :breadcrumbs="breadcrumbs" />

        <SupplierIncompleteAlert
            :missing-fields="props.context.supplier_missing_fields"
            @open-settings="settingsDrawer.open"
        />

        <RecurringInvoiceForm :context="context" />

        <InvoiceSettingsDrawer
            :open="settingsDrawer.state.isOpen"
            :status="settingsDrawer.state.status"
            :settings="settingsDrawer.state.settings"
            @close="settingsDrawer.close"
            @retry="settingsDrawer.open"
            @saved="onSettingsSaved"
        />
    </AppLayout>
</template>
