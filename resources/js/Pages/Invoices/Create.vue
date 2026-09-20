<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import InvoiceForm from '@/Components/Invoices/InvoiceForm.vue';
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
    { label: t('invoices'), url: '/invoices' },
    { label: t('invoice_add') },
];

const settingsDrawer = useInvoiceSettingsDrawer();

function onSettingsSaved(): void {
    settingsDrawer.close();
    router.reload({ only: ['context'] });
}
</script>

<template>
    <Header :title="t('invoice_add')" :breadcrumbs="breadcrumbs" />

    <SupplierIncompleteAlert
        :missing-fields="props.context.supplier_missing_fields"
        @open-settings="settingsDrawer.open"
    />

    <InvoiceForm :context="context" />

    <InvoiceSettingsDrawer
        :open="settingsDrawer.state.isOpen"
        :status="settingsDrawer.state.status"
        :settings="settingsDrawer.state.settings"
        :signature="settingsDrawer.state.signature"
        :constraints="settingsDrawer.state.constraints"
        @close="settingsDrawer.close"
        @retry="settingsDrawer.open"
        @saved="onSettingsSaved"
    />
</template>
