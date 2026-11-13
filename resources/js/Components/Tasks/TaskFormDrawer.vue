<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import SideDrawer from '@/Components/SideDrawer.vue';
import TaskForm from './TaskForm.vue';

defineProps<{
    open: boolean;
    context: App.Data.Tasks.TaskFormContextData;
    initialStatus?: App.Enums.TaskStatusEnum | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();
</script>

<template>
    <SideDrawer :open="open" :title="t('task_add')" @close="emit('close')">
        <TaskForm :context="context" :initial-status="initialStatus" @saved="emit('close')" @cancel="emit('close')" />
    </SideDrawer>
</template>
