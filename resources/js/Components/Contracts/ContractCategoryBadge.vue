<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { contractCategoryKey } from '@/utils/enums';

defineProps<{
    category: App.Enums.ContractCategoryEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <span class="badge badge-sm badge-outline">{{ t(contractCategoryKey(category)) }}</span>
</template>
