<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import CheckCircleIcon from '~icons/material-symbols/check-circle-outline-rounded';
import Header from '@/Layouts/Header.vue';
import { TableFilters } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState.vue';
import PageMainAction from '@/Components/PageMainAction.vue';
import ViewModeSwitch from '@/Components/ViewModeSwitch.vue';
import TaskBoard from '@/Components/Tasks/TaskBoard.vue';
import TaskFormDrawer from '@/Components/Tasks/TaskFormDrawer.vue';
import TaskTable from '@/Components/Tasks/TaskTable.vue';
import { KG_ICONS } from '@/icons/kegosIcons';

import { useTaskFilters } from '@/Composables/useTaskFilters';
import { useTaskStatusChange } from '@/Composables/useTaskStatusChange';
import { useTaskTransition } from '@/Composables/useTaskTransition';
import type { Breadcrumb, Paginator } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    view: App.Enums.TaskIndexViewEnum;
    tasks: Paginator<App.Data.Tasks.TaskListItemData> | null;
    board: App.Data.Tasks.TaskBoardData | null;
    filters?: Record<string, unknown>;
    context: App.Data.Tasks.TaskFormContextData;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('tasks') }];

const ui = reactive({
    createOpen: false,
    createStatus: null as App.Enums.TaskStatusEnum | null,
});

const { processingId, transition } = useTaskTransition();
const { pending, announcement, change } = useTaskStatusChange();
const { query, definitions, reloadOnly, hasActiveFilters, chipSignature, changeFilter, clearFilters, changeView } =
    useTaskFilters({
        view: () => props.view,
        filters: () => props.filters,
        context: () => props.context,
    });

const canCreate = computed(() => props.context.companies.length > 0);
const viewOptions = computed(() => [
    { value: 'table', label: t('tasks_view_table'), icon: KG_ICONS.task.viewTable },
    { value: 'board', label: t('tasks_view_board'), icon: KG_ICONS.task.viewBoard },
]);
const total = computed(() =>
    props.view === 'board'
        ? (props.board?.columns.reduce((sum, column) => sum + column.total, 0) ?? 0)
        : (props.tasks?.total ?? 0),
);
const showEmptyState = computed(() => total.value === 0 && !hasActiveFilters.value);

function openCreate(status: App.Enums.TaskStatusEnum | null = null): void {
    ui.createStatus = status;
    ui.createOpen = true;
}
</script>

<template>
    <Header :title="t('tasks')" :breadcrumbs="breadcrumbs">
        <template #actions>
            <ViewModeSwitch
                :model-value="view"
                :options="viewOptions"
                :label="t('tasks_view_mode')"
                @update:model-value="changeView($event as App.Enums.TaskIndexViewEnum)"
            />
            <PageMainAction v-if="canCreate" :label="t('task_add')" @click="openCreate()" />
        </template>
    </Header>

    <div class="sr-only" role="status" aria-live="polite">{{ announcement }}</div>

    <EmptyState
        v-if="showEmptyState"
        :title="t('tasks_empty')"
        :description="t('tasks_empty_hint')"
        :icon="CheckCircleIcon"
    >
        <template #cta>
            <button v-if="canCreate" type="button" class="btn btn-primary" @click="openCreate()">
                {{ t('task_add') }}
            </button>
        </template>
    </EmptyState>

    <div v-else class="flex flex-col gap-4">
        <TableFilters
            :key="`${view}:${chipSignature}`"
            :filters="definitions"
            :query-filters="query.filters"
            @change="changeFilter"
            @clear="clearFilters"
        />

        <div v-if="view === 'table' && tasks" class="card bg-base-100">
            <div class="card-body">
                <TaskTable
                    :tasks="tasks"
                    :filters="definitions"
                    :reload-only="reloadOnly"
                    :processing-id="processingId"
                    @complete="transition($event, 'complete')"
                    @status="change"
                />
            </div>
        </div>

        <TaskBoard
            v-else-if="board"
            :board="board"
            :pending="pending"
            :can-create="canCreate"
            :filters="query.filters"
            @move="change"
            @create="openCreate"
        />
    </div>

    <TaskFormDrawer
        :open="ui.createOpen"
        :context="context"
        :initial-status="ui.createStatus"
        @close="ui.createOpen = false"
    />
</template>
