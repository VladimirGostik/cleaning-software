<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CheckIcon from '~icons/material-symbols/check-outline-rounded';
import EyeIcon from '~icons/material-symbols/visibility-outline-rounded';
import DataTable from '@/Components/DataTable/DataTable.vue';
import InitialsAvatar from '@/Components/InitialsAvatar.vue';
import { formatDatetime } from '@/utils/date';
import { isTaskRunListWorthy } from '@/utils/taskAi';
import { isTaskOverdue } from '@/utils/taskBoard';
import { TASK_PANEL_PROPS, useTaskPanel } from '@/Composables/useTaskPanel';
import type { FilterConfig, Paginator, TableColumn } from '@/types/table';
import TaskAiRunBadge from './TaskAiRunBadge.vue';
import TaskPriorityBadge from './TaskPriorityBadge.vue';
import TaskStatusMenu from './TaskStatusMenu.vue';
import TaskSubjectLink from './TaskSubjectLink.vue';

type Row = App.Data.Tasks.TaskListItemData;

defineProps<{
    tasks: Paginator<Row>;
    filters: FilterConfig[];
    reloadOnly: string[];
    processingId: string | null;
}>();

const emit = defineEmits<{
    complete: [taskId: string];
    status: [task: Row, status: App.Enums.TaskStatusEnum];
}>();

const { t } = useI18n();
const { href: taskHref, open: openTask } = useTaskPanel();

const columns: TableColumn<Row>[] = [
    { key: 'title', label: t('task_title'), sortable: true, primary: true },
    { key: 'status', label: t('status'), sortable: true, filter: 'status' },
    { key: 'priority', label: t('task_priority'), sortable: true, hideBelow: 1440, filter: 'priority' },
    { key: 'due_at', label: t('task_due_at'), sortable: true, nowrap: true, filter: 'due' },
    { key: 'tenant_name', label: t('task_company'), sortable: false, filter: 'kind' },
    { key: 'assignee_name', label: t('task_assignee'), sortable: false, hideBelow: 1440, filter: 'assignee' },
    { key: 'subject', label: t('task_subject'), sortable: false, hideBelow: 1440 },
];
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="tasks"
        :filters="filters"
        :enable-filters="false"
        :reload-only="reloadOnly"
        :row-href="(row) => taskHref(row.id)"
        :open-row="(row) => openTask(row.id)"
    >
        <template #cell-title="{ row }">
            <Link
                :href="taskHref(row.id)"
                :only="TASK_PANEL_PROPS"
                preserve-state
                preserve-scroll
                class="link link-hover font-medium"
                >{{ row.title }}</Link
            >
        </template>

        <template #cell-status="{ row }">
            <div class="flex flex-wrap items-center gap-1">
                <TaskStatusMenu
                    variant="chip"
                    :status="row.status"
                    :editable="row.can_update"
                    :disabled="processingId === row.id"
                    @select="emit('status', row, $event)"
                />
                <TaskAiRunBadge
                    v-if="row.ai_run_status && isTaskRunListWorthy(row.ai_run_status)"
                    :status="row.ai_run_status"
                />
            </div>
        </template>

        <template #cell-priority="{ row }">
            <TaskPriorityBadge :priority="row.priority" />
        </template>

        <template #cell-due_at="{ row }">
            <span v-if="row.due_at" :class="{ 'text-error': isTaskOverdue(row) }">
                <template v-if="isTaskOverdue(row)">{{ t('task_overdue') }}: </template>{{ formatDatetime(row.due_at) }}
            </span>
            <template v-else>{{ t('empty_dash') }}</template>
        </template>

        <template #cell-tenant_name="{ row }">
            {{ row.is_global ? t('task_kind_global') : (row.tenant_name ?? t('empty_dash')) }}
        </template>

        <template #cell-assignee_name="{ row }">
            {{ row.assignee_name ?? t('empty_dash') }}
        </template>

        <template #cell-subject="{ row }">
            <TaskSubjectLink v-if="row.subject" :subject="row.subject" />
            <span v-else>{{ t('empty_dash') }}</span>
        </template>

        <template #card="{ row }">
            <div class="flex flex-col gap-2">
                <Link
                    :href="taskHref(row.id)"
                    :only="TASK_PANEL_PROPS"
                    preserve-state
                    preserve-scroll
                    class="link link-hover break-words font-medium"
                    >{{ row.title }}</Link
                >
                <div class="flex flex-wrap items-center gap-1">
                    <TaskStatusMenu
                        variant="chip"
                        :status="row.status"
                        :editable="row.can_update"
                        :disabled="processingId === row.id"
                        @select="emit('status', row, $event)"
                    />
                    <TaskPriorityBadge :priority="row.priority" />
                    <TaskAiRunBadge
                        v-if="row.ai_run_status && isTaskRunListWorthy(row.ai_run_status)"
                        :status="row.ai_run_status"
                    />
                </div>
                <div class="flex items-center justify-between gap-2 text-sm text-muted">
                    <span v-if="row.due_at" :class="{ 'text-error': isTaskOverdue(row) }">
                        <template v-if="isTaskOverdue(row)">{{ t('task_overdue') }}: </template
                        >{{ formatDatetime(row.due_at) }}
                    </span>
                    <span v-else>{{ t('empty_dash') }}</span>
                    <InitialsAvatar v-if="row.assignee_name" :name="row.assignee_name" />
                </div>
            </div>
        </template>

        <template #buttons="{ row }">
            <button
                v-if="row.can_update && row.is_active"
                type="button"
                class="btn btn-ghost btn-sm btn-square"
                :title="t('task_complete')"
                :aria-label="t('task_complete')"
                :disabled="processingId === row.id"
                @click="emit('complete', row.id)"
            >
                <CheckIcon />
            </button>

            <Link
                :href="taskHref(row.id)"
                :only="TASK_PANEL_PROPS"
                preserve-state
                preserve-scroll
                class="btn btn-ghost btn-sm btn-square"
                :title="t('view')"
                :aria-label="t('view')"
            >
                <EyeIcon />
            </Link>
        </template>
    </DataTable>
</template>
