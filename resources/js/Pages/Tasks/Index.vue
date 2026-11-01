<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import CheckCircleIcon from '~icons/material-symbols/check-circle-outline-rounded';
import CheckIcon from '~icons/material-symbols/check-outline-rounded';
import EyeIcon from '~icons/material-symbols/visibility-outline-rounded';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import TaskAiRunBadge from '@/Components/Tasks/TaskAiRunBadge.vue';
import TaskStatusBadge from '@/Components/Tasks/TaskStatusBadge.vue';
import TaskPriorityBadge from '@/Components/Tasks/TaskPriorityBadge.vue';
import TaskSubjectLink from '@/Components/Tasks/TaskSubjectLink.vue';
import TaskFormDrawer from '@/Components/Tasks/TaskFormDrawer.vue';

import { useTaskTransition } from '@/Composables/useTaskTransition';
import { usePageProps } from '@/Composables/usePageProps';
import { useAuthorization } from '@/Composables/useAuthorization';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { formatDatetime } from '@/utils/date';
import { isTaskRunListWorthy } from '@/utils/taskAi';
import { TASK_PRIORITIES, TASK_STATUSES, taskPriorityKey, taskStatusKey } from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    tasks: Paginator<App.Data.Tasks.TaskListItemData>;
    filters?: Record<string, unknown>;
    context: App.Data.Tasks.TaskFormContextData;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('tasks') }];

const ui = reactive({
    createOpen: false,
});

const pageProps = usePageProps();
const { processingId, transition } = useTaskTransition();

const canCreate = computed(() => props.context.companies.length > 0);
const canComplete = computed(() => allows('edit tasks'));
const assigneeOptions = computed(() => {
    const activeId = pageProps.value.tenant.active?.id;
    const members = activeId ? (props.context.members[activeId] ?? []) : [];

    return members.map((m) => ({ value: m.id, label: m.name }));
});
const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(() => props.tasks.total === 0 && !hasActiveFilters.value);

const columns: TableColumn<App.Data.Tasks.TaskListItemData>[] = [
    { key: 'title', label: t('task_title'), sortable: true, primary: true },
    { key: 'status', label: t('status'), sortable: true },
    { key: 'priority', label: t('task_priority'), sortable: false, hideBelow: 1440 },
    { key: 'due_at', label: t('task_due_at'), sortable: true, nowrap: true },
    { key: 'tenant_name', label: t('task_company'), sortable: false },
    { key: 'assignee_name', label: t('task_assignee'), sortable: false, hideBelow: 1440 },
    { key: 'subject', label: t('task_subject'), sortable: false, hideBelow: 1440 },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
    {
        property: 'status',
        label: t('status'),
        type: 'select',
        placeholder: t('status'),
        defaultOperator: '=',
        options: TASK_STATUSES.map((v) => ({ value: v, label: t(taskStatusKey(v)) })),
    },
    {
        property: 'priority',
        label: t('task_priority'),
        type: 'select',
        placeholder: t('task_priority'),
        defaultOperator: '=',
        options: TASK_PRIORITIES.map((v) => ({ value: v, label: t(taskPriorityKey(v)) })),
    },
    ...(assigneeOptions.value.length > 0
        ? [
              {
                  property: 'assigned_membership_id',
                  label: t('task_assignee'),
                  type: 'select' as const,
                  placeholder: t('task_assignee'),
                  defaultOperator: '=' as const,
                  options: assigneeOptions.value,
              },
          ]
        : []),
    {
        property: 'kind',
        label: t('task_kind'),
        type: 'select',
        placeholder: t('task_kind'),
        defaultOperator: '=',
        options: [
            { value: 'firm', label: t('task_kind_firm') },
            { value: 'global', label: t('task_kind_global') },
        ],
    },
    {
        property: 'due_at',
        label: t('task_due_at'),
        type: 'date',
        placeholder: t('task_due_at'),
        defaultOperator: '>=',
        operators: ['>=', '<=', 'between'],
    },
]);
</script>

<template>
    <Header :title="t('tasks')" :breadcrumbs="breadcrumbs">
        <template #actions>
            <button v-if="canCreate" type="button" class="btn btn-secondary btn-sm" @click="ui.createOpen = true">
                {{ t('task_add') }}
            </button>
        </template>
    </Header>

    <EmptyState
        v-if="showEmptyState"
        :title="t('tasks_empty')"
        :description="t('tasks_empty_hint')"
        :icon="CheckCircleIcon"
    >
        <template #cta>
            <button v-if="canCreate" type="button" class="btn btn-primary btn-sm" @click="ui.createOpen = true">
                {{ t('task_add') }}
            </button>
        </template>
    </EmptyState>

    <div v-else class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <DataTable :columns="columns" :rows="tasks" :filters="filterDefinitions">
                <template #cell-title="{ row }">
                    <Link :href="`/tasks/${row.id}`" class="link link-hover font-medium">{{ row.title }}</Link>
                </template>

                <template #cell-status="{ row }">
                    <div class="flex flex-wrap items-center gap-1">
                        <TaskStatusBadge :status="row.status" />
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
                    {{ row.due_at ? formatDatetime(row.due_at) : t('empty_dash') }}
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

                <template #buttons="{ row }">
                    <button
                        v-if="canComplete && row.status === 'open'"
                        type="button"
                        class="btn btn-ghost btn-xs"
                        :title="t('task_complete')"
                        :disabled="processingId === row.id"
                        @click="transition(row.id, 'complete')"
                    >
                        <CheckIcon class="size-5" />
                    </button>

                    <Link :href="`/tasks/${row.id}`" class="btn btn-ghost btn-xs" :title="t('view')">
                        <EyeIcon class="size-5" />
                    </Link>
                </template>
            </DataTable>
        </div>
    </div>

    <TaskFormDrawer :open="ui.createOpen" :context="context" @close="ui.createOpen = false" />
</template>
