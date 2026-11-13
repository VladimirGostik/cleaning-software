<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CheckCircleIcon from '~icons/material-symbols/check-circle-outline-rounded';
import Header from '@/Layouts/Header.vue';
import { TableFilters } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState.vue';
import PageMainAction from '@/Components/PageMainAction.vue';
import ViewModeSwitch from '@/Components/ViewModeSwitch.vue';
import TaskDetailBody from '@/Components/Tasks/TaskDetailBody.vue';
import TaskDetailPanel from '@/Components/Tasks/TaskDetailPanel.vue';
import TaskBoard from '@/Components/Tasks/TaskBoard.vue';
import TaskTable from '@/Components/Tasks/TaskTable.vue';
import { KG_ICONS } from '@/icons/kegosIcons';

import { useAuthorization } from '@/Composables/useAuthorization';
import { usePageProps } from '@/Composables/usePageProps';
import { settleTaskCreate, useTaskCreate } from '@/Composables/useTaskCreate';
import { useTaskFilters } from '@/Composables/useTaskFilters';
import { useTaskPanel } from '@/Composables/useTaskPanel';
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
    selected_task?: App.Data.Tasks.TaskDetailData | null;
    selected_attachments?: App.Data.Tasks.TaskAttachmentListData | null;
    selected_thread?: App.Data.Tasks.TaskThreadData | null;
    selected_activity?: App.Data.Tasks.TaskActivityFeedData | null;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('tasks') }];

const panel = useTaskPanel();
const pageProps = usePageProps();
const { allows } = useAuthorization();
const creator = useTaskCreate({
    context: () => props.context,
    activeTenantId: () => pageProps.value.tenant.active?.id ?? null,
    selectedTaskId: () => props.selected_task?.id ?? null,
    mayEditInActive: () => allows('edit tasks'),
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

const inSession = computed(() => creator.state.value !== 'closed');
const panelTask = computed(() => {
    if (!props.selected_task || !props.selected_attachments || !props.selected_thread) return null;
    if (!inSession.value) return props.selected_task;

    return props.selected_task.id === creator.createdId.value ? props.selected_task : null;
});
const panelOpen = computed(() => inSession.value || panelTask.value !== null);
const panelKey = computed(() => (inSession.value ? `new-${creator.sessionKey.value}` : (panelTask.value?.id ?? '')));
const panelHeading = computed(() => (inSession.value && !panelTask.value ? t('task_add') : t('task_detail_heading')));

async function openCreate(status: App.Enums.TaskStatusEnum | null = null): Promise<void> {
    if (!(await settleTaskCreate())) return;
    if (panel.selectedId.value) await panel.close();

    creator.open(status);
}

async function closePanel(): Promise<void> {
    if (!inSession.value) {
        await panel.close();
        return;
    }

    if (!(await creator.leave())) return;
    if (panel.selectedId.value) await panel.close();
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

    <TaskDetailPanel v-if="panelOpen" :heading="panelHeading" @close="closePanel">
        <TaskDetailBody
            :key="panelKey"
            :task="panelTask"
            :attachments="panelTask ? (selected_attachments ?? null) : null"
            :context="context"
            :thread="panelTask ? (selected_thread ?? null) : null"
            :activity="selected_activity"
            :creator="inSession ? creator : null"
        />
    </TaskDetailPanel>
</template>
