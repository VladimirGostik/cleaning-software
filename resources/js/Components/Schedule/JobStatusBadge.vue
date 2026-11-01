<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { jobStatusKey } from '@/utils/enums';
import { JOB_STATUS_TONE } from './jobStatusStyle';

const props = defineProps<{
    status: App.Enums.JobStatusEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip :tone="JOB_STATUS_TONE[props.status]" :label="t(jobStatusKey(props.status))" size="sm" />
</template>
