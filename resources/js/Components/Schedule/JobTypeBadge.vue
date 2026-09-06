<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { jobTypeKey } from '@/utils/enums';

defineProps<{
    type: App.Enums.JobTypeEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <span class="badge badge-sm badge-outline">{{ t(jobTypeKey(type)) }}</span>
</template>
