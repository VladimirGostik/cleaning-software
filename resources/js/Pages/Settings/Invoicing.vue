<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import InvoiceSettingsForm from '@/Components/Invoices/InvoiceSettingsForm.vue';

import type { Breadcrumb } from '@/types';

defineProps<{
    settings: App.Data.Invoices.InvoiceSettingsData;
    signature: App.Data.Tenants.TenantSignatureData | null;
    // eslint-disable-next-line vue/prop-name-casing -- Inertia page prop, must match the server key exactly
    signature_constraints: App.Data.Invoices.InvoiceSignatureConstraintsData;
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('invoicing_settings') }];
</script>

<template>
    <Header :title="t('invoicing_settings')" :breadcrumbs="breadcrumbs" />
    <p class="mb-6 text-base-content/60">{{ t('invoice_settings_subtitle') }}</p>

    <InvoiceSettingsForm :settings="settings" :signature="signature" :signature-constraints="signature_constraints" />
</template>
