<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import SideDrawer from '@/Components/SideDrawer.vue';
import TaskForm from './TaskForm.vue';

const props = defineProps<{
    open: boolean;
    context: App.Data.Tasks.TaskFormContextData;
    task?: App.Data.Tasks.TaskDetailData | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const title = computed(() => (props.task ? t('task_edit') : t('task_add')));
</script>

<template>
    <SideDrawer :open="open" :title="title" @close="emit('close')">
        <TaskForm :context="context" :task="task" @saved="emit('close')" @cancel="emit('close')" />
    </SideDrawer>
</template>
