<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowPathIcon from '~icons/material-symbols/autorenew-outline-rounded';
import CheckIcon from '~icons/material-symbols/check-outline-rounded';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import TaskStatusMenu from '@/Components/Tasks/TaskStatusMenu.vue';
import TaskPriorityBadge from '@/Components/Tasks/TaskPriorityBadge.vue';
import TaskSubjectLink from '@/Components/Tasks/TaskSubjectLink.vue';
import TaskFormDrawer from '@/Components/Tasks/TaskFormDrawer.vue';
import TaskAssignPanel from '@/Components/Tasks/TaskAssignPanel.vue';
import TaskAttachmentsCard from '@/Components/Tasks/TaskAttachmentsCard.vue';
import TaskAiCard from '@/Components/Tasks/TaskAiCard.vue';
import TaskCommentsCard from '@/Components/Tasks/TaskCommentsCard.vue';

import { useTaskAttachmentUpload } from '@/Composables/useTaskAttachmentUpload';
import { useTaskAiRun } from '@/Composables/useTaskAiRun';
import { useTaskStatusChange } from '@/Composables/useTaskStatusChange';
import { useTaskTransition } from '@/Composables/useTaskTransition';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { KG_ICONS } from '@/icons/kegosIcons';
import { formatDate, formatDatetime } from '@/utils/date';
import { taskReminderSlotKey } from '@/utils/enums';
import { findReminderSlot, formatSlotTime } from '@/utils/taskReminder';
import { shouldShowTaskAiCard } from '@/utils/taskAi';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    task: App.Data.Tasks.TaskDetailData;
    attachments: App.Data.Tasks.TaskAttachmentListData;
    context: App.Data.Tasks.TaskFormContextData;
    thread: App.Data.Tasks.TaskThreadData;
}>();

const reminderLabel = computed(() => {
    if (!props.task.remind_on || !props.task.remind_slot) return t('empty_dash');

    const found = findReminderSlot(props.context.reminder_slots, props.task.remind_slot);
    const name = t(taskReminderSlotKey(props.task.remind_slot));
    const slot = found ? t('task_reminder_slot_option', { slot: name, time: formatSlotTime(found.hour) }) : name;

    return t('task_reminder_at_slot', { date: formatDate(props.task.remind_on), slot });
});

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('tasks'), url: '/tasks' },
    { label: props.task.title },
]);

const ui = reactive({
    editOpen: false,
    moreOpen: false,
});

const { processingId, transition } = useTaskTransition();
const { pending, change } = useTaskStatusChange();
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

const shownStatus = computed(() => pending.value[props.task.id] ?? props.task.status);
const assignMembers = computed(() => (props.task.tenant_id ? (props.context.members[props.task.tenant_id] ?? []) : []));
const canAssign = computed(() => props.task.can.assign && !props.task.is_global && assignMembers.value.length > 0);

const { state, openModal, closeModal, confirmDelete, getModalTitle, getModalDescription } =
    useDeleteConfirm<App.Data.Tasks.TaskDetailData>({
        resolveUrl: (task) => `/tasks/${task.id}`,
        getTitle: () => t('task_delete'),
        getDescription: (task) => t('task_delete_confirm', { title: task.title }),
    });
</script>

<template>
    <Header :title="task.title" :breadcrumbs="breadcrumbs">
        <template #actions>
            <button
                v-if="task.can.update && task.is_active"
                type="button"
                class="btn btn-secondary"
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

            <button
                v-if="task.can.update"
                type="button"
                class="btn btn-ghost hidden sm:inline-flex"
                :disabled="uploading"
                @click="ui.editOpen = true"
            >
                <PencilSquareIcon />
                {{ t('edit') }}
            </button>

            <button
                v-if="task.can.delete"
                type="button"
                class="btn btn-error hidden sm:inline-flex"
                :disabled="uploading"
                @click="openModal(task)"
            >
                <TrashIcon />
                {{ t('delete') }}
            </button>

            <div v-if="task.can.update || task.can.delete" class="sm:hidden">
                <AnchoredPopover v-model:open="ui.moreOpen" class="p-1">
                    <template #trigger="{ toggle, open }">
                        <button
                            type="button"
                            class="btn btn-ghost btn-square"
                            aria-haspopup="menu"
                            :aria-expanded="open"
                            :aria-label="t('more_actions')"
                            :title="t('more_actions')"
                            @click="toggle"
                        >
                            <KG_ICONS.adminAction.more />
                        </button>
                    </template>

                    <div role="menu" class="flex min-w-40 flex-col">
                        <button
                            v-if="task.can.update"
                            type="button"
                            role="menuitem"
                            class="flex min-h-11 items-center gap-2 rounded-tile-sm px-3 text-left hover:bg-base-100"
                            :disabled="uploading"
                            @click="((ui.moreOpen = false), (ui.editOpen = true))"
                        >
                            <PencilSquareIcon />
                            {{ t('edit') }}
                        </button>
                        <button
                            v-if="task.can.delete"
                            type="button"
                            role="menuitem"
                            class="flex min-h-11 items-center gap-2 rounded-tile-sm px-3 text-left text-error hover:bg-base-100"
                            :disabled="uploading"
                            @click="((ui.moreOpen = false), openModal(task))"
                        >
                            <TrashIcon />
                            {{ t('delete') }}
                        </button>
                    </div>
                </AnchoredPopover>
            </div>
        </template>
    </Header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="card bg-base-100">
                <div class="card-body">
                    <h2 class="card-title">{{ t('description') }}</h2>
                    <p v-if="task.description" class="whitespace-pre-wrap">{{ task.description }}</p>
                    <p v-else class="text-muted">{{ t('empty_dash') }}</p>
                </div>
            </div>

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

            <TaskAttachmentsCard
                :task-id="task.id"
                :list="attachments"
                :items="uploadItems"
                :uploading="uploading"
                @add="add"
                @dismiss="dismiss"
            />

            <TaskCommentsCard :task-id="task.id" :thread="thread" :uploading="uploading" />
        </div>

        <div class="order-first space-y-4 lg:order-none">
            <div class="card bg-base-100">
                <div class="card-body">
                    <h2 class="card-title">{{ t('task_details') }}</h2>

                    <div class="flex flex-wrap gap-2">
                        <TaskStatusMenu
                            variant="chip"
                            :status="shownStatus"
                            :editable="task.can.update"
                            :disabled="pending[task.id] !== undefined"
                            @select="change(task, $event)"
                        />
                        <TaskPriorityBadge :priority="task.priority" />
                    </div>

                    <dl class="mt-2 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">{{ t('task_company') }}</dt>
                            <dd>
                                {{ task.is_global ? t('task_kind_global') : (task.tenant_name ?? t('empty_dash')) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">{{ t('task_due_at') }}</dt>
                            <dd>{{ task.due_at ? formatDatetime(task.due_at) : t('empty_dash') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">{{ t('task_remind_at') }}</dt>
                            <dd>{{ reminderLabel }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">{{ t('task_assignee') }}</dt>
                            <dd>{{ task.assignee_name ?? t('task_unassigned') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">{{ t('task_subject') }}</dt>
                            <dd>
                                <TaskSubjectLink v-if="task.subject" :subject="task.subject" />
                                <span v-else>{{ t('empty_dash') }}</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">{{ t('task_created_by') }}</dt>
                            <dd>{{ task.created_by_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">{{ t('created_at') }}</dt>
                            <dd>{{ formatDatetime(task.created_at) }}</dd>
                        </div>
                        <div v-if="task.completed_at" class="flex justify-between gap-4">
                            <dt class="text-muted">{{ t('task_completed_at') }}</dt>
                            <dd>{{ formatDatetime(task.completed_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div v-if="canAssign" class="card bg-base-100">
                <div class="card-body">
                    <TaskAssignPanel :task="task" :members="assignMembers" />
                </div>
            </div>
        </div>
    </div>

    <TaskFormDrawer
        v-if="task.can.update"
        :open="ui.editOpen"
        :context="context"
        :task="task"
        @close="ui.editOpen = false"
    />

    <ConfirmDeleteModal
        :is-open="state.isOpen"
        :title="getModalTitle()"
        :description="getModalDescription()"
        @cancel="closeModal"
        @confirm="confirmDelete"
    />
</template>
