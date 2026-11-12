<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import DocumentTemplateWorkspace from '@/Components/DocumentTemplates/DocumentTemplateWorkspace.vue';
import type { Breadcrumb } from '@/types';

defineProps<{
    type: App.Enums.DocumentTypeEnum;
    // eslint-disable-next-line vue/prop-name-casing -- prop name is the BE contract key
    employment_type: App.Enums.EmploymentContractTypeEnum | null;
    isGlobal: boolean;
    tokens: App.Data.DocumentTemplates.DocumentTokenCatalogData;
    letterhead: App.Data.Documents.DocumentLetterheadData;
    footer: string[];
    // eslint-disable-next-line vue/prop-name-casing -- prop name is the BE contract key
    quote_footer: string[];
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('document_templates'), url: '/settings/document-templates' },
    { label: t('document_template_add') },
]);
</script>

<template>
    <Header :title="t('document_template_add')" :breadcrumbs="breadcrumbs" />

    <DocumentTemplateWorkspace
        :catalog="tokens"
        :initial-type="type"
        :initial-employment-type="employment_type"
        :is-global="isGlobal"
        :letterhead="letterhead"
        :footer="footer"
        :quote-footer="quote_footer"
    />
</template>
