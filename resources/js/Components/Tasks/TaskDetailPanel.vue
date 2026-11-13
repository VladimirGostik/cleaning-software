<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import SideDrawer from '@/Components/SideDrawer.vue';
import TaskDetailBody from '@/Components/Tasks/TaskDetailBody.vue';

defineProps<{
    task: App.Data.Tasks.TaskDetailData;
    attachments: App.Data.Tasks.TaskAttachmentListData;
    context: App.Data.Tasks.TaskFormContextData;
    thread: App.Data.Tasks.TaskThreadData;
    activity?: App.Data.Tasks.TaskActivityFeedData | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();
</script>

<template>
    <SideDrawer open :modal="false" :title="t('task_detail_heading')" @close="emit('close')">
        <TaskDetailBody
            :key="task.id"
            :task="task"
            :attachments="attachments"
            :context="context"
            :thread="thread"
            :activity="activity"
        />
    </SideDrawer>
</template>
