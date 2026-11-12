<script setup lang="ts">
import SideDrawer from '@/Components/SideDrawer.vue';
import TaskDetailBody from '@/Components/Tasks/TaskDetailBody.vue';

defineProps<{
    task: App.Data.Tasks.TaskDetailData;
    attachments: App.Data.Tasks.TaskAttachmentListData;
    context: App.Data.Tasks.TaskFormContextData;
    thread: App.Data.Tasks.TaskThreadData;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <SideDrawer open :modal="false" :title="task.title" @close="emit('close')">
        <TaskDetailBody :key="task.id" :task="task" :attachments="attachments" :context="context" :thread="thread" />
    </SideDrawer>
</template>
