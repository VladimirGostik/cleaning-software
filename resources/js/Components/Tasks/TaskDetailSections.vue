<script setup lang="ts">
import { useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import TaskAiCard from '@/Components/Tasks/TaskAiCard.vue';
import TaskAttachmentsCard from '@/Components/Tasks/TaskAttachmentsCard.vue';
import TaskActivitySection from '@/Components/Tasks/TaskActivitySection.vue';
import TaskDescriptionField from '@/Components/Tasks/TaskDescriptionField.vue';
import TaskPanelSection from '@/Components/Tasks/TaskPanelSection.vue';
import TaskPropertiesList from '@/Components/Tasks/TaskPropertiesList.vue';

import { useTaskAttachmentUpload } from '@/Composables/useTaskAttachmentUpload';
import { useTaskAiRun } from '@/Composables/useTaskAiRun';
import type { useTaskInlineEdit } from '@/Composables/useTaskInlineEdit';
import { shouldShowTaskAiCard } from '@/utils/taskAi';

const props = defineProps<{
    task: App.Data.Tasks.TaskDetailData;
    attachments: App.Data.Tasks.TaskAttachmentListData;
    context: App.Data.Tasks.TaskFormContextData;
    thread: App.Data.Tasks.TaskThreadData;
    activity?: App.Data.Tasks.TaskActivityFeedData | null;
    edit: ReturnType<typeof useTaskInlineEdit>;
}>();

const emit = defineEmits<{
    'update:uploading': [uploading: boolean];
}>();

const { t } = useI18n();

const description = useTemplateRef<{ focus: () => void }>('description');

const {
    items: uploadItems,
    uploading,
    add,
    dismiss,
} = useTaskAttachmentUpload({
    taskId: props.task.id,
    list: () => props.attachments,
});
const {
    starting,
    errors: aiErrors,
    statusCheckFailed,
    start,
} = useTaskAiRun({
    taskId: props.task.id,
    thread: () => props.thread,
});

watch(uploading, (value) => emit('update:uploading', value));

defineExpose({
    focusDescription: () => description.value?.focus(),
});
</script>

<template>
    <TaskPropertiesList
        :key="`properties-${edit.resets.value}`"
        class="-mt-3"
        :task="task"
        :context="context"
        :editable="task.can.update"
        :disabled="uploading"
        :errors="edit.errors.value"
        @save="edit.save($event)"
    />

    <TaskPanelSection v-if="task.can.update || task.description" :title="t('description')">
        <TaskDescriptionField
            ref="description"
            :key="`description-${edit.resets.value}`"
            :description="task.description"
            :editable="task.can.update"
            :disabled="uploading"
            :error="edit.errors.value.description"
            @save="
                (value, debounced) =>
                    debounced ? edit.saveDebounced({ description: value }) : edit.save({ description: value })
            "
        />
    </TaskPanelSection>

    <TaskAttachmentsCard
        :task-id="task.id"
        :list="attachments"
        :items="uploadItems"
        :uploading="uploading"
        @add="add"
        @dismiss="dismiss"
    />

    <TaskAiCard
        v-if="shouldShowTaskAiCard(thread)"
        :thread="thread"
        :is-global="task.is_global"
        :uploading="uploading"
        :starting="starting"
        :errors="aiErrors"
        :status-check-failed="statusCheckFailed"
        @start="start"
    />

    <TaskActivitySection
        :task="task"
        :thread="thread"
        :attachments="attachments"
        :activity="activity"
        :uploading="uploading"
    />
</template>
