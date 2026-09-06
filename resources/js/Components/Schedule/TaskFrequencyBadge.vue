<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { taskFrequencyKey } from '@/utils/enums';

defineProps<{
    frequency: App.Enums.TaskFrequencyEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <span class="badge badge-sm badge-ghost">{{ t(taskFrequencyKey(frequency)) }}</span>
</template>
