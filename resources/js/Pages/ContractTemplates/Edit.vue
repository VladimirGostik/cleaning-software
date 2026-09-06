<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import ContractTemplateForm from '@/Components/ContractTemplates/ContractTemplateForm.vue';

import type { Breadcrumb } from '@/types';

const props = defineProps<{
    template: App.Data.ContractTemplates.ContractTemplateDetailData;
    tokens: App.Data.Contracts.PlaceholderCatalogData;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('contract_templates'), url: '/contract-templates' },
    { label: props.template.name, url: `/contract-templates/${props.template.id}` },
    { label: t('contract_template_edit') },
]);
</script>

<template>
    <AppLayout>
        <Header :title="t('contract_template_edit')" :breadcrumbs="breadcrumbs" />

        <ContractTemplateForm :template="template" :tokens="tokens" />
    </AppLayout>
</template>
