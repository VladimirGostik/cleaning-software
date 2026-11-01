<script setup lang="ts">
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';
import { KG_ICONS } from '@/icons/kegosIcons';
import { taskStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.TaskStatusEnum;
}>();

const { t } = useI18n();

const STATUS: Record<App.Enums.TaskStatusEnum, { tone: StatusTone; icon?: Component }> = {
    open: { tone: 'planned' },
    done: { tone: 'done' },
    cancelled: { tone: 'neutral', icon: KG_ICONS.status.cancelled },
};

const entry = computed(() => STATUS[props.status]);
</script>

<template>
    <StatusChip :tone="entry.tone" :icon="entry.icon" :label="t(taskStatusKey(props.status))" size="sm" />
</template>
