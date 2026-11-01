<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';
import { taskRunStatusKey } from '@/utils/enums';

defineProps<{
    status: App.Enums.TaskRunStatusEnum;
}>();

const { t } = useI18n();

const TONE: Record<App.Enums.TaskRunStatusEnum, StatusTone> = {
    queued: 'planned',
    running: 'in_progress',
    done: 'done',
    needs_input: 'warning',
    awaiting_confirmation: 'warning',
    could_not_finish: 'warning',
    blocked: 'danger',
    failed: 'danger',
};
</script>

<template>
    <StatusChip :tone="TONE[status]" :label="t(taskRunStatusKey(status))" size="sm" />
</template>
