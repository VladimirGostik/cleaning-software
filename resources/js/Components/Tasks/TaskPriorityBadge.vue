<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';
import { taskPriorityKey } from '@/utils/enums';

const props = defineProps<{
    priority: App.Enums.TaskPriorityEnum;
}>();

const { t } = useI18n();

const STATUS: Record<App.Enums.TaskPriorityEnum, { tone: StatusTone }> = {
    low: { tone: 'neutral' },
    normal: { tone: 'neutral' },
    high: { tone: 'warning' },
};

const entry = computed(() => STATUS[props.priority]);
</script>

<template>
    <StatusChip :tone="entry.tone" :label="t(taskPriorityKey(props.priority))" size="sm" />
</template>
