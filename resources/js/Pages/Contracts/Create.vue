<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import ContractForm from '@/Components/Contracts/ContractForm.vue';

import type { Breadcrumb } from '@/types';

defineProps<{
    context: App.Data.Contracts.ContractFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('contracts'), url: '/contracts' },
    { label: t('contract_add') },
];
</script>

<template>
    <AppLayout>
        <Header :title="t('contract_add')" :breadcrumbs="breadcrumbs" />

        <ContractForm :context="context" />
    </AppLayout>
</template>
