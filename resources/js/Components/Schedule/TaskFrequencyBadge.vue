<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { taskFrequencyKey } from '@/utils/enums';

const props = defineProps<{
    frequency: App.Enums.TaskFrequencyEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip tone="neutral" :label="t(taskFrequencyKey(props.frequency))" size="sm" />
</template>
