<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
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
    <Header :title="t('contract_edit')" :breadcrumbs="breadcrumbs" />

    <div v-if="!contract.is_editable" class="alert alert-warning">
        <span>{{ t('contract_not_editable') }}</span>
        <Link :href="`/contracts/${contract.id}`" class="link link-hover font-medium">{{ t('view') }}</Link>
    </div>

    <ContractForm v-else :context="context" :contract="contract" />
</template>
