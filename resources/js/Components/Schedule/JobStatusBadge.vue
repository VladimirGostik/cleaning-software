<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { jobStatusKey } from '@/utils/enums';
import { JOB_STATUS_STYLE } from './jobStatusStyle';

const props = defineProps<{
    status: App.Enums.JobStatusEnum;
}>();

const { t } = useI18n();

const badgeClass = computed(() => JOB_STATUS_STYLE[props.status].badge);
</script>

<template>
    <span class="badge badge-sm" :class="badgeClass">{{ t(jobStatusKey(status)) }}</span>
</template>
