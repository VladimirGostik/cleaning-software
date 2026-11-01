<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { contractCategoryKey } from '@/utils/enums';

const props = defineProps<{
    category: App.Enums.ContractCategoryEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip tone="neutral" :label="t(contractCategoryKey(props.category))" size="sm" />
</template>
