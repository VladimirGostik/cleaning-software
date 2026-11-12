<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import ContractForm from '@/Components/Contracts/ContractForm.vue';
import ContractFileForm from '@/Components/Contracts/ContractFileForm.vue';
import ContractSourceSelector from '@/Components/Contracts/ContractSourceSelector.vue';
import { fileFlowAvailable } from '@/utils/contracts';

import type { Breadcrumb } from '@/types';

const props = defineProps<{
    context: App.Data.Contracts.ContractFormContextData;
    prefill: App.Data.Contracts.ContractCreatePrefillData;
}>();

const { t } = useI18n();

const source = ref<App.Enums.ContractSourceEnum>('generated');
const selectorAvailable = fileFlowAvailable(props.prefill);

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('contracts'), url: '/contracts' },
    { label: t('contract_add') },
];
</script>

<template>
    <Header :title="t('contract_add')" :breadcrumbs="breadcrumbs" />

    <div class="space-y-4">
        <ContractSourceSelector v-if="selectorAvailable" v-model="source" />

        <ContractFileForm v-if="selectorAvailable && source === 'file'" :context="context" :prefill="prefill" />
        <ContractForm v-else :context="context" :prefill="prefill" />
    </div>
</template>
