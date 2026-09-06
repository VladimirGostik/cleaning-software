<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { EyeIcon, PencilSquareIcon, UserGroupIcon } from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ObjectStatusBadge from '@/Components/Objects/ObjectStatusBadge.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { employmentTypeKey } from '@/utils/enums';
import { formatDate } from '@/utils/date';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    employees: Paginator<App.Data.Employees.EmployeeListItemData>;
    filters?: Record<string, unknown>;
    filterOptions: {
        roles: App.Data.RoleListItemData[];
    };
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('employees') }];

const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(() => props.employees.total === 0 && !hasActiveFilters.value);

const columns: TableColumn<App.Data.Employees.EmployeeListItemData>[] = [
    { key: 'last_name', label: t('name'), sortable: true, value: (r) => r.display_name },
    { key: 'email', label: t('email'), sortable: false },
    { key: 'role_name', label: t('employee_role'), sortable: false },
    { key: 'position', label: t('position'), sortable: false },
    { key: 'employment_type', label: t('employee_employment_type'), sortable: false },
    {
        key: 'upcoming_jobs_count',
        label: t('employee_upcoming_jobs'),
        sortable: false,
        headerClass: 'text-right',
        cellClass: 'text-right font-mono',
    },
    { key: 'is_active', label: t('status'), sortable: true },
    { key: 'joined_at', label: t('employee_joined_at'), sortable: true },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
    {
        property: 'role',
        label: t('employee_role'),
        type: 'select',
        placeholder: t('select_role'),
        defaultOperator: '=',
        options: props.filterOptions.roles.map((r) => ({ value: r.name, label: r.name })),
    },
    { property: 'is_active', label: t('status'), type: 'boolean', defaultOperator: '=' },
    {
        property: 'joined_at',
        label: t('employee_joined_at'),
        type: 'date',
        placeholder: t('employee_joined_at'),
        defaultOperator: '>=',
        operators: ['>=', '<=', 'between'],
    },
]);
</script>

<template>
    <AppLayout>
        <Header :title="t('employees')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <a v-if="allows('create employees')" href="/employees/create" class="btn btn-primary btn-sm">
                    {{ t('employee_add') }}
                </a>
            </template>
        </Header>

        <EmptyState
            v-if="showEmptyState"
            :title="t('employees_empty')"
            :description="t('employees_empty_hint')"
            :icon="UserGroupIcon"
        >
            <template v-if="allows('create employees')" #cta>
                <a href="/employees/create" class="btn btn-primary btn-sm">{{ t('employee_add') }}</a>
            </template>
        </EmptyState>

        <div v-else class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable :columns="columns" :rows="employees" :filters="filterDefinitions" row-key="id">
                    <template #cell-last_name="{ row }">
                        <a :href="`/employees/${row.id}`" class="link link-hover font-medium">{{ row.display_name }}</a>
                    </template>

                    <template #cell-role_name="{ row }">
                        {{ row.role_name ?? t('empty_dash') }}
                    </template>

                    <template #cell-position="{ row }">
                        {{ row.position ?? t('empty_dash') }}
                    </template>

                    <template #cell-employment_type="{ row }">
                        {{ row.employment_type ? t(employmentTypeKey(row.employment_type)) : t('empty_dash') }}
                    </template>

                    <template #cell-is_active="{ row }">
                        <ObjectStatusBadge :is-active="row.is_active" />
                    </template>

                    <template #cell-joined_at="{ row }">
                        {{ formatDate(row.joined_at) }}
                    </template>

                    <template #buttons="{ row }">
                        <a :href="`/employees/${row.id}`" class="btn btn-ghost btn-xs" :title="t('view')">
                            <EyeIcon class="size-4" />
                        </a>
                        <a
                            v-if="allows('edit employees')"
                            :href="`/employees/${row.id}/edit`"
                            class="btn btn-ghost btn-xs"
                            :title="t('edit')"
                        >
                            <PencilSquareIcon class="size-4" />
                        </a>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
