<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import EyeIcon from '~icons/material-symbols/visibility-outline-rounded';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import UserGroupIcon from '~icons/material-symbols/groups-outline-rounded';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ObjectStatusBadge from '@/Components/Objects/ObjectStatusBadge.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import EmployeeAccessBadge from '@/Components/Employees/EmployeeAccessBadge.vue';
import { EMPLOYEE_TYPES, employeeTypeKey } from '@/utils/enums';
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
    { key: 'last_name', label: t('name'), sortable: true, primary: true, value: (r) => r.display_name },
    { key: 'email', label: t('email'), sortable: false, hideBelow: 1600 },
    { key: 'role_name', label: t('employee_role'), sortable: false },
    { key: 'position', label: t('position'), sortable: false, hideBelow: 1440 },
    { key: 'employee_type', label: t('type'), sortable: false },
    {
        key: 'upcoming_jobs_count',
        label: t('employee_col_upcoming_jobs'),
        sortable: false,
        align: 'right',
    },
    { key: 'is_active', label: t('status'), sortable: true },
    { key: 'joined_at', label: t('employee_joined_at'), sortable: true, hideBelow: 1440, nowrap: true },
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
    {
        property: 'employee_type',
        label: t('type'),
        type: 'select',
        defaultOperator: '=',
        operators: ['='],
        options: EMPLOYEE_TYPES.map((v) => ({ value: v, label: t(employeeTypeKey(v)) })),
    },
    {
        property: 'has_access',
        label: t('employee_section_access'),
        type: 'select',
        defaultOperator: '=',
        operators: ['='],
        options: [
            { value: '1', label: t('employee_access_active') },
            { value: '0', label: t('employee_access_none') },
        ],
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
    <Header :title="t('employees')" :breadcrumbs="breadcrumbs">
        <template #actions>
            <Link v-if="allows('create employees')" href="/employees/create" class="btn btn-secondary btn-sm">
                {{ t('employee_add') }}
            </Link>
        </template>
    </Header>

    <EmptyState
        v-if="showEmptyState"
        :title="t('employees_empty')"
        :description="t('employees_empty_hint')"
        :icon="UserGroupIcon"
    >
        <template v-if="allows('create employees')" #cta>
            <Link href="/employees/create" class="btn btn-primary btn-sm">{{ t('employee_add') }}</Link>
        </template>
    </EmptyState>

    <div v-else class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <DataTable
                :columns="columns"
                :rows="employees"
                :filters="filterDefinitions"
                row-key="id"
                :row-href="(row) => `/employees/${row.id}`"
            >
                <template #cell-last_name="{ row }">
                    <Link :href="`/employees/${row.id}`" class="link link-hover font-medium">{{
                        row.display_name
                    }}</Link>
                </template>

                <template #cell-email="{ row }">
                    {{ row.email ?? t('empty_dash') }}
                </template>

                <template #cell-role_name="{ row }">
                    <EmployeeAccessBadge v-if="!row.has_access" :has-access="false" :invitation-pending="false" />
                    <template v-else>
                        {{ row.role_name ?? t('empty_dash') }}
                        <EmployeeAccessBadge
                            v-if="row.invitation_pending"
                            :has-access="true"
                            :invitation-pending="true"
                        />
                    </template>
                </template>

                <template #cell-position="{ row }">
                    {{ row.position ?? t('empty_dash') }}
                </template>

                <template #cell-employee_type="{ row }">
                    {{ row.employee_type ? t(employeeTypeKey(row.employee_type)) : t('empty_dash') }}
                </template>

                <template #cell-is_active="{ row }">
                    <ObjectStatusBadge :is-active="row.is_active" />
                </template>

                <template #cell-joined_at="{ row }">
                    {{ formatDate(row.joined_at) }}
                </template>

                <template #buttons="{ row }">
                    <Link :href="`/employees/${row.id}`" class="btn btn-ghost btn-xs" :title="t('view')">
                        <EyeIcon class="size-5" />
                    </Link>
                    <Link
                        v-if="allows('edit employees')"
                        :href="`/employees/${row.id}/edit`"
                        class="btn btn-ghost btn-xs"
                        :title="t('edit')"
                    >
                        <PencilSquareIcon class="size-5" />
                    </Link>
                </template>
            </DataTable>
        </div>
    </div>
</template>
