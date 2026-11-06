<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';
import { taskStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.TaskStatusEnum;
}>();

const { t } = useI18n();

const STATUS: Record<App.Enums.TaskStatusEnum, { tone: StatusTone }> = {
    open: { tone: 'planned' },
    in_progress: { tone: 'in_progress' },
    waiting: { tone: 'warning' },
    nice_to_have: { tone: 'neutral' },
    done: { tone: 'done' },
};

const entry = computed(() => STATUS[props.status]);
</script>

<template>
    <StatusChip :tone="entry.tone" :label="t(taskStatusKey(props.status))" size="sm" />
</template>
