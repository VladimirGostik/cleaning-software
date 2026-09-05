<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import { formatDatetime } from '@/utils/date';
import { EyeIcon } from '@heroicons/vue/24/solid';
import type { Paginator, Breadcrumb, TableColumn } from '@/types';
import type { FilterConfig } from '@/types/table';

const { t } = useI18n();

defineProps<{
    activities: Paginator<App.Data.ActivityLogListItemData>;
    query?: Record<string, unknown>;
}>();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('audit_logs') },
];

const columns: TableColumn[] = [
    { key: 'created_at', label: t('created_at'), sortable: true },
    { key: 'description', label: t('description'), sortable: true },
    { key: 'subject_type', label: t('subject_type') },
    { key: 'event', label: t('event') },
    { key: 'causer_name', label: t('causer'), sortable: true },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    {
        property: 'subject_type',
        label: t('subject_type'),
        type: 'text',
        placeholder: t('subject_type'),
        defaultOperator: '~',
    },
    {
        property: 'created_at',
        label: t('created_at'),
        type: 'date',
        defaultOperator: '>=',
        operators: ['>=', '<=', 'between'],
    },
]);
</script>

<template>
    <AppLayout>
        <Header
            :title="t('audit_logs')"
            :breadcrumbs="breadcrumbs"
        />

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable
                    :columns="columns"
                    :rows="activities"
                    :filters="filterDefinitions"
                    route-name="audit-logs.index"
                >
                    <template #cell-created_at="{ value }">
                        {{ formatDatetime(value as string | null) }}
                    </template>

                    <template #cell-causer_name="{ row }">
                        <div>
                            <span class="font-medium">{{
                                (row as App.Data.ActivityLogListItemData).causer_name ?? '-'
                            }}</span>
                            <br />
                            <span class="text-xs text-base-content/60">
                                {{ (row as App.Data.ActivityLogListItemData).causer_email ?? '' }}
                            </span>
                        </div>
                    </template>

                    <template #cell-event="{ value }">
                        <span
                            v-if="value"
                            class="badge badge-ghost badge-sm"
                        >
                            {{ value }}
                        </span>
                        <span v-else>-</span>
                    </template>

                    <template #cell-description="{ value }">
                        {{ value }}
                    </template>

                    <template #buttons="{ row }">
                        <a
                            :href="`/audit-logs/${(row as App.Data.ActivityLogListItemData).id}`"
                            class="btn btn-ghost btn-xs"
                            :title="t('details')"
                        >
                            <EyeIcon class="size-4" />
                        </a>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
