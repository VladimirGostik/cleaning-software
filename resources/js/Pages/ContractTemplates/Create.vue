<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import ContractTemplateForm from '@/Components/ContractTemplates/ContractTemplateForm.vue';

import type { Breadcrumb } from '@/types';

defineProps<{
    tokens: App.Data.Contracts.PlaceholderCatalogData;
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('contract_templates'), url: '/contract-templates' },
    { label: t('contract_template_add') },
];
</script>

<template>
    <AppLayout>
        <Header :title="t('contract_template_add')" :breadcrumbs="breadcrumbs" />

        <ContractTemplateForm :tokens="tokens" />
    </AppLayout>
</template>
