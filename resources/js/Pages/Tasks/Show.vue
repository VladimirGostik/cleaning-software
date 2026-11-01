<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowPathIcon from '~icons/material-symbols/autorenew-outline-rounded';
import CheckIcon from '~icons/material-symbols/check-outline-rounded';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import TaskStatusBadge from '@/Components/Tasks/TaskStatusBadge.vue';
import TaskPriorityBadge from '@/Components/Tasks/TaskPriorityBadge.vue';
import TaskSubjectLink from '@/Components/Tasks/TaskSubjectLink.vue';
import TaskFormDrawer from '@/Components/Tasks/TaskFormDrawer.vue';
import TaskAssignPanel from '@/Components/Tasks/TaskAssignPanel.vue';

import { useTaskTransition } from '@/Composables/useTaskTransition';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { formatDatetime } from '@/utils/date';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    task: App.Data.Tasks.TaskDetailData;
    context: App.Data.Tasks.TaskFormContextData;
}>();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('tasks'), url: '/tasks' },
    { label: props.task.title },
]);

const ui = reactive({
    editOpen: false,
});

const { processingId, transition } = useTaskTransition();
const processing = computed(() => processingId.value !== null);

const isOpen = computed(() => props.task.status === 'open');
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
                v-if="task.can.update && isOpen"
                type="button"
                class="btn btn-secondary btn-sm"
                :disabled="processing"
                @click="transition(task.id, 'complete')"
            >
                <CheckIcon class="size-5" />
                {{ t('task_complete') }}
            </button>

            <button
                v-if="task.can.update && !isOpen"
                type="button"
                class="btn btn-ghost btn-sm"
                :disabled="processing"
                @click="transition(task.id, 'reopen')"
            >
                <ArrowPathIcon class="size-5" />
                {{ t('task_reopen') }}
            </button>

            <button v-if="task.can.update" type="button" class="btn btn-ghost btn-sm" @click="ui.editOpen = true">
                <PencilSquareIcon class="size-5" />
                {{ t('edit') }}
            </button>

            <button v-if="task.can.delete" type="button" class="btn btn-error btn-sm" @click="openModal(task)">
                <TrashIcon class="size-5" />
                {{ t('delete') }}
            </button>
        </template>
    </Header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('description') }}</h2>
                    <p v-if="task.description" class="whitespace-pre-wrap">{{ task.description }}</p>
                    <p v-else class="text-base-content/60">{{ t('empty_dash') }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('task_details') }}</h2>

                    <div class="flex flex-wrap gap-2">
                        <TaskStatusBadge :status="task.status" />
                        <TaskPriorityBadge :priority="task.priority" />
                    </div>

                    <dl class="mt-2 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('task_company') }}</dt>
                            <dd>
                                {{ task.is_global ? t('task_kind_global') : (task.tenant_name ?? t('empty_dash')) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('task_due_at') }}</dt>
                            <dd>{{ task.due_at ? formatDatetime(task.due_at) : t('empty_dash') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('task_remind_at') }}</dt>
                            <dd>{{ task.remind_at ? formatDatetime(task.remind_at) : t('empty_dash') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('task_assignee') }}</dt>
                            <dd>{{ task.assignee_name ?? t('task_unassigned') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('task_subject') }}</dt>
                            <dd>
                                <TaskSubjectLink v-if="task.subject" :subject="task.subject" />
                                <span v-else>{{ t('empty_dash') }}</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('task_created_by') }}</dt>
                            <dd>{{ task.created_by_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('created_at') }}</dt>
                            <dd>{{ formatDatetime(task.created_at) }}</dd>
                        </div>
                        <div v-if="task.completed_at" class="flex justify-between gap-4">
                            <dt class="text-base-content/60">{{ t('task_completed_at') }}</dt>
                            <dd>{{ formatDatetime(task.completed_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div v-if="canAssign" class="card bg-base-100 shadow-sm">
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
