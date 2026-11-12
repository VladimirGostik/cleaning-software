<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import EyeIcon from '~icons/material-symbols/visibility-outline-rounded';

import DataTable from '@/Components/DataTable/DataTable.vue';
import JobTypeBadge from './JobTypeBadge.vue';
import JobStatusBadge from './JobStatusBadge.vue';

import { formatDate } from '@/utils/date';
import type { Paginator } from '@/types';
import type { TableColumn } from '@/types/table';

const props = defineProps<{
    jobs: Paginator<App.Data.Schedule.JobListItemData>;
}>();

const { t } = useI18n();

function slice5(value: string | null): string {
    return value ? value.slice(0, 5) : '';
}

const columns: TableColumn<App.Data.Schedule.JobListItemData>[] = [
    { key: 'scheduled_date', label: t('date'), sortable: true, nowrap: true },
    { key: 'start_time', label: t('schedule_col_time'), sortable: false, value: (r) => r.start_time, nowrap: true },
    { key: 'object_name', label: t('object'), sortable: false, primary: true },
    { key: 'client_name', label: t('client'), sortable: false },
    { key: 'assignee_display_name', label: t('schedule_col_assignee'), sortable: false },
    { key: 'type', label: t('type'), sortable: true, nowrap: true },
    { key: 'status', label: t('status'), sortable: true, nowrap: true },
];
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="props.jobs"
        :enable-filters="false"
        row-key="id"
        :row-href="(row) => `/jobs/${row.id}`"
        :reload-only="['jobs', 'filters']"
    >
        <template #cell-scheduled_date="{ row }">
            <Link :href="`/jobs/${row.id}`" class="link link-hover font-medium tabular-nums">
                {{ formatDate(row.scheduled_date) }}
            </Link>
        </template>

        <template #cell-start_time="{ row }">
            <span v-if="row.start_time"
                >{{ slice5(row.start_time) }}{{ row.end_time ? ` – ${slice5(row.end_time)}` : '' }}</span
            >
            <span v-else>{{ t('empty_dash') }}</span>
        </template>

        <template #cell-object_name="{ row }">
            <Link :href="`/objects/${row.cleaning_object_id}`" class="link link-hover">{{ row.object_name }}</Link>
        </template>

        <template #cell-client_name="{ row }">
            {{ row.client_name ?? t('empty_dash') }}
        </template>

        <template #cell-assignee_display_name="{ row }">
            <span v-if="row.assignee_display_name">{{ row.assignee_display_name }}</span>
            <span v-else class="text-muted">{{ t('job_status_unassigned') }}</span>
        </template>

        <template #cell-type="{ row }">
            <JobTypeBadge :type="row.type" />
        </template>

        <template #cell-status="{ row }">
            <JobStatusBadge :status="row.status" />
            <span v-if="row.is_invoiced" class="kg-chip kg-chip--done kg-chip--sm ml-1">{{
                t('schedule_invoiced')
            }}</span>
        </template>

        <template #buttons="{ row }">
            <Link
                :href="`/jobs/${row.id}`"
                class="btn btn-ghost btn-sm btn-square"
                :title="t('view')"
                :aria-label="t('view')"
            >
                <EyeIcon />
            </Link>
        </template>
    </DataTable>
</template>
