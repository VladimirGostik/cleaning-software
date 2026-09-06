<script setup lang="ts">
import { computed } from 'vue';
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

const settingsDrawer = useInvoiceSettingsDrawer();

function onSettingsSaved(): void {
    settingsDrawer.close();
    router.reload({ only: ['context'] });
}
</script>

<template>
    <AppLayout>
        <Header :title="t('recurring_invoice_edit')" :breadcrumbs="breadcrumbs" />

        <div v-if="!isEditable" class="alert alert-warning">
            <span>{{ t('recurring_invoice_not_editable') }}</span>
        </div>

        <template v-else>
            <SupplierIncompleteAlert
                :missing-fields="context.supplier_missing_fields"
                @open-settings="settingsDrawer.open"
            />

            <RecurringInvoiceForm :context="context" :recurring-invoice="recurringInvoice" />
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
