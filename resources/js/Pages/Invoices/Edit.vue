<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import InvoiceForm from '@/Components/Invoices/InvoiceForm.vue';
import SupplierIncompleteAlert from '@/Components/Invoices/SupplierIncompleteAlert.vue';
import InvoiceSettingsDrawer from '@/Components/Invoices/InvoiceSettingsDrawer.vue';

import { useInvoiceSettingsDrawer } from '@/Composables/useInvoiceSettingsDrawer';
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

const settingsDrawer = useInvoiceSettingsDrawer();

function onSettingsSaved(): void {
    settingsDrawer.close();
    router.reload({ only: ['context'] });
}
</script>

<template>
    <AppLayout>
        <Header :title="t('invoice_edit')" :breadcrumbs="breadcrumbs" />

        <div v-if="invoice.status !== 'draft'" class="alert alert-warning">
            <span>{{ t('invoice_not_editable') }}</span>
        </div>

        <template v-else>
            <SupplierIncompleteAlert
                :missing-fields="context.supplier_missing_fields"
                @open-settings="settingsDrawer.open"
            />

            <InvoiceForm :context="context" :invoice="invoice" />
        </template>

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
