<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { jobTypeKey } from '@/utils/enums';

const props = defineProps<{
    type: App.Enums.JobTypeEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip tone="neutral" :label="t(jobTypeKey(props.type))" size="sm" />
</template>
