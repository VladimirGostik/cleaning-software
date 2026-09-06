<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { EyeIcon } from '@heroicons/vue/24/outline';

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
    { key: 'scheduled_date', label: t('date'), sortable: true },
    { key: 'start_time', label: t('schedule_col_time'), sortable: false, value: (r) => r.start_time },
    { key: 'object_name', label: t('object'), sortable: false },
    { key: 'client_name', label: t('client'), sortable: false },
    { key: 'assignee_display_name', label: t('schedule_col_assignee'), sortable: false },
    { key: 'type', label: t('type'), sortable: true },
    { key: 'status', label: t('status'), sortable: true },
];
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="props.jobs"
        :enable-filters="false"
        row-key="id"
        :reload-only="['jobs', 'filters']"
    >
        <template #cell-scheduled_date="{ row }">
            <a :href="`/jobs/${row.id}`" class="link link-hover font-medium font-mono">
                {{ formatDate(row.scheduled_date) }}
            </a>
        </template>

        <template #cell-start_time="{ row }">
            <span v-if="row.start_time"
                >{{ slice5(row.start_time) }}{{ row.end_time ? ` – ${slice5(row.end_time)}` : '' }}</span
            >
            <span v-else>{{ t('empty_dash') }}</span>
        </template>

        <template #cell-object_name="{ row }">
            <a :href="`/objects/${row.cleaning_object_id}`" class="link link-hover">{{ row.object_name }}</a>
        </template>

        <template #cell-client_name="{ row }">
            {{ row.client_name ?? t('empty_dash') }}
        </template>

        <template #cell-assignee_display_name="{ row }">
            <span v-if="row.assignee_display_name">{{ row.assignee_display_name }}</span>
            <span v-else class="text-base-content/50">{{ t('job_status_unassigned') }}</span>
        </template>

        <template #cell-type="{ row }">
            <JobTypeBadge :type="row.type" />
        </template>

        <template #cell-status="{ row }">
            <JobStatusBadge :status="row.status" />
            <span v-if="row.is_invoiced" class="badge badge-xs badge-success ml-1">{{ t('schedule_invoiced') }}</span>
        </template>

        <template #buttons="{ row }">
            <a :href="`/jobs/${row.id}`" class="btn btn-ghost btn-xs" :title="t('view')" :aria-label="t('view')">
                <EyeIcon class="size-4" />
            </a>
        </template>
    </DataTable>
</template>
