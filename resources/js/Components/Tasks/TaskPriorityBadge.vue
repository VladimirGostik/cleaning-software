<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { taskPriorityKey } from '@/utils/enums';

const props = defineProps<{
    priority: App.Enums.TaskPriorityEnum;
}>();

const { t } = useI18n();

const STYLE: Record<App.Enums.TaskPriorityEnum, string> = {
    low: 'badge-ghost',
    normal: 'badge-outline',
    high: 'badge-warning',
};

const badgeClass = computed(() => STYLE[props.priority]);
</script>

<template>
    <span class="badge badge-sm" :class="badgeClass">{{ t(taskPriorityKey(priority)) }}</span>
</template>
