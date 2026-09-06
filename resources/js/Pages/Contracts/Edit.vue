<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import ContractForm from '@/Components/Contracts/ContractForm.vue';

import type { Breadcrumb } from '@/types';

const props = defineProps<{
    contract: App.Data.Contracts.ContractDetailData;
    context: App.Data.Contracts.ContractFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('contracts'), url: '/contracts' },
    { label: props.contract.title, url: `/contracts/${props.contract.id}` },
    { label: t('contract_edit') },
]);
</script>

<template>
    <AppLayout>
        <Header :title="t('contract_edit')" :breadcrumbs="breadcrumbs" />

        <div v-if="!contract.is_editable" class="alert alert-warning">
            <span>{{ t('contract_not_editable') }}</span>
            <a :href="`/contracts/${contract.id}`" class="link link-hover font-medium">{{ t('view') }}</a>
        </div>

        <ContractForm v-else :context="context" :contract="contract" />
    </AppLayout>
</template>
