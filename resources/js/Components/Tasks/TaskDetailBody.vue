<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowPathIcon from '~icons/material-symbols/autorenew-outline-rounded';
import CheckIcon from '~icons/material-symbols/check-outline-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import TaskAiCard from '@/Components/Tasks/TaskAiCard.vue';
import TaskAttachmentsCard from '@/Components/Tasks/TaskAttachmentsCard.vue';
import TaskActivitySection from '@/Components/Tasks/TaskActivitySection.vue';
import TaskDescriptionField from '@/Components/Tasks/TaskDescriptionField.vue';
import TaskPanelSection from '@/Components/Tasks/TaskPanelSection.vue';
import TaskPropertiesList from '@/Components/Tasks/TaskPropertiesList.vue';
import TaskSaveIndicator from '@/Components/Tasks/TaskSaveIndicator.vue';
import TaskTitleField from '@/Components/Tasks/TaskTitleField.vue';

import { useTaskAttachmentUpload } from '@/Composables/useTaskAttachmentUpload';
import { useTaskAiRun } from '@/Composables/useTaskAiRun';
import { flushActiveTaskEdit, taskEditIdle, useTaskInlineEdit } from '@/Composables/useTaskInlineEdit';
import { useTaskTransition } from '@/Composables/useTaskTransition';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { shouldShowTaskAiCard } from '@/utils/taskAi';

const { t } = useI18n();

const props = defineProps<{
    task: App.Data.Tasks.TaskDetailData;
    attachments: App.Data.Tasks.TaskAttachmentListData;
    context: App.Data.Tasks.TaskFormContextData;
    thread: App.Data.Tasks.TaskThreadData;
    activity?: App.Data.Tasks.TaskActivityFeedData | null;
}>();

const { processingId, transition } = useTaskTransition();
const edit = useTaskInlineEdit({ taskId: props.task.id });
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
const processing = computed(() => processingId.value !== null || uploading.value);

const { state, openModal, closeModal, confirmDelete, getModalTitle, getModalDescription } =
    useDeleteConfirm<App.Data.Tasks.TaskDetailData>({
        resolveUrl: (task) => `/tasks/${task.id}`,
        getTitle: () => t('task_delete'),
        getDescription: (task) => t('task_delete_confirm', { title: task.title }),
    });

async function deleteAfterFlush(): Promise<void> {
    if (!taskEditIdle()) await flushActiveTaskEdit();
    confirmDelete();
}
</script>

<template>
    <div class="space-y-6 px-6 pb-6">
        <div class="flex items-center gap-2">
            <button
                v-if="task.can.update && task.is_active"
                type="button"
                class="btn btn-primary"
                :disabled="processing"
                @click="transition(task.id, 'complete')"
            >
                <CheckIcon />
                {{ t('task_complete') }}
            </button>

            <button
                v-if="task.can.update && !task.is_active"
                type="button"
                class="btn btn-ghost"
                :disabled="processing"
                @click="transition(task.id, 'reopen')"
            >
                <ArrowPathIcon />
                {{ t('task_reopen') }}
            </button>

            <TaskSaveIndicator class="ms-auto" :state="edit.state.value" />

            <button
                v-if="task.can.delete"
                type="button"
                class="btn btn-ghost btn-square"
                :disabled="uploading"
                :aria-label="t('delete')"
                :title="t('delete')"
                @click="openModal(task)"
            >
                <TrashIcon />
            </button>
        </div>

        <div class="space-y-3">
            <TaskTitleField
                :key="`title-${edit.resets.value}`"
                :title="task.title"
                :editable="task.can.update"
                :disabled="uploading"
                :error="edit.errors.value.title"
                @save="edit.save({ title: $event })"
            />

            <TaskPropertiesList
                :key="`properties-${edit.resets.value}`"
                :task="task"
                :context="context"
                :editable="task.can.update"
                :disabled="uploading"
                :errors="edit.errors.value"
                @save="edit.save($event)"
            />
        </div>

        <TaskPanelSection v-if="task.can.update || task.description" :title="t('description')">
            <TaskDescriptionField
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
    </div>

    <ConfirmDeleteModal
        :is-open="state.isOpen"
        :title="getModalTitle()"
        :description="getModalDescription()"
        @cancel="closeModal"
        @confirm="deleteAfterFlush"
    />
</template>
