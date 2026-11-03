<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import RecurringInvoiceForm from '@/Components/RecurringInvoices/RecurringInvoiceForm.vue';
import SupplierIncompleteAlert from '@/Components/Invoices/SupplierIncompleteAlert.vue';
import InvoiceSettingsDrawer from '@/Components/Invoices/InvoiceSettingsDrawer.vue';

import { useInvoiceSettingsDrawer } from '@/Composables/useInvoiceSettingsDrawer';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    context: App.Data.Invoices.InvoiceFormContextData;
    prefill: App.Data.RecurringInvoices.RecurringInvoicePrefillData | null;
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
    <Header :title="t('recurring_invoice_add')" :breadcrumbs="breadcrumbs" />

    <SupplierIncompleteAlert
        :missing-fields="props.context.supplier_missing_fields"
        @open-settings="settingsDrawer.open"
    />

    <div v-if="prefill" class="alert alert-info mb-4">
        <span>{{ t('recurring_invoice_prefilled_from_quote') }}</span>
        <Link :href="`/quotes/${prefill.quote_id}`" class="link">{{ t('recurring_invoice_prefill_open_quote') }}</Link>
    </div>

    <RecurringInvoiceForm :context="context" :prefill="prefill" />

    <InvoiceSettingsDrawer
        :open="settingsDrawer.state.isOpen"
        :status="settingsDrawer.state.status"
        :settings="settingsDrawer.state.settings"
        :signature="settingsDrawer.state.signature"
        :constraints="settingsDrawer.state.constraints"
        :mail-placeholders="settingsDrawer.state.mailPlaceholders"
        @close="settingsDrawer.close"
        @retry="settingsDrawer.open"
        @saved="onSettingsSaved"
    />
</template>
