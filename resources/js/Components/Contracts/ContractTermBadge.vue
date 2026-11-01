<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { contractTermTypeKey } from '@/utils/enums';

const props = defineProps<{
    termType: App.Enums.ContractTermTypeEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip tone="neutral" :label="t(contractTermTypeKey(props.termType))" size="sm" />
</template>
