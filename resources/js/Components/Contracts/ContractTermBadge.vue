<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { contractTermTypeKey } from '@/utils/enums';

defineProps<{
    termType: App.Enums.ContractTermTypeEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <span class="badge badge-sm badge-ghost">{{ t(contractTermTypeKey(termType)) }}</span>
</template>
