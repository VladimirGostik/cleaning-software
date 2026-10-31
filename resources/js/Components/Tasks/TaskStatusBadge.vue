<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { taskStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.TaskStatusEnum;
}>();

const { t } = useI18n();

const STYLE: Record<App.Enums.TaskStatusEnum, string> = {
    open: 'badge-info',
    done: 'badge-success',
    cancelled: 'badge-ghost',
};

const badgeClass = computed(() => STYLE[props.status]);
</script>

<template>
    <span class="badge badge-sm" :class="badgeClass">{{ t(taskStatusKey(status)) }}</span>
</template>
