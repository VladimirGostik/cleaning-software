<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import PageMainAction from '@/Components/PageMainAction.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';

import { formatDatetime } from '@/utils/date';
import { useAuthorization } from '@/Composables/useAuthorization';
import type { Breadcrumb, Paginator, TableColumn } from '@/types';
import type { FilterConfig } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    users: Paginator<App.Data.UserListItemData>;
    filters?: Record<string, unknown>;
    filterOptions: {
        roles: App.Data.RoleListItemData[];
    };
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('users') }];

const columns: TableColumn[] = [
    { key: 'name', label: t('name'), sortable: true },
    { key: 'email', label: t('email'), sortable: true },
    { key: 'roles', label: t('roles'), sortable: false },
    { key: 'is_active', label: t('membership_active'), sortable: true },
    { key: 'created_at', label: t('created_at'), sortable: true, class: 'whitespace-nowrap' },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    {
        property: 'search',
        label: t('search'),
        type: 'text',
        placeholder: t('search'),
        defaultOperator: '~',
    },
    {
        property: 'name',
        label: t('name'),
        type: 'text',
        placeholder: t('name'),
        defaultOperator: '~',
    },
    {
        property: 'email',
        label: t('email'),
        type: 'text',
        placeholder: t('email'),
        defaultOperator: '~',
    },
    {
        property: 'role',
        label: t('roles'),
        type: 'select',
        placeholder: t('select_role'),
        defaultOperator: '=',
        options: props.filterOptions.roles.map((role) => ({
            label: role.name,
            value: role.name,
        })),
    },
    {
        property: 'is_active',
        label: t('membership_active'),
        type: 'select',
        placeholder: t('select_status'),
        defaultOperator: '=',
        options: [
            { label: t('active'), value: '1' },
            { label: t('inactive'), value: '0' },
        ],
    },
    {
        property: 'created_at',
        label: t('created_at'),
        type: 'date',
        placeholder: t('created_at'),
        defaultOperator: '>=',
        operators: ['>=', '<=', 'between'],
    },
]);
</script>

<template>
    <Header :title="t('users')" :breadcrumbs="breadcrumbs">
        <template #actions>
            <PageMainAction
                v-if="allows('create employees') && allows('manage employee_access')"
                :label="t('create')"
                href="/users/create"
            />
        </template>
    </Header>

    <div class="card bg-base-100">
        <div class="card-body">
            <DataTable
                :columns="columns"
                :rows="users"
                :filters="filterDefinitions"
                :can-edit="allows('edit employees')"
                :can-delete="allows('delete employees') && allows('manage employee_access')"
                :edit-url="(row: App.Data.UserListItemData) => `/users/${row.id}/edit`"
                :delete-url="(row: App.Data.UserListItemData) => `/users/${row.id}`"
                :row-href="
                    (row: App.Data.UserListItemData) => (allows('edit employees') ? `/users/${row.id}/edit` : null)
                "
            >
                <template #cell-roles="{ row }">
                    <div class="flex flex-wrap gap-1">
                        <span v-for="role in row.roles" :key="role" class="kg-chip kg-chip--neutral kg-chip--sm">
                            {{ role }}
                        </span>

                        <span v-if="row.roles.length === 0" class="text-muted text-sm">
                            {{ t('no_roles') }}
                        </span>
                    </div>
                </template>

                <template #cell-is_active="{ value }">
                    <span v-if="value" class="kg-chip kg-chip--done kg-chip--sm">
                        {{ t('membership_active') }}
                    </span>
                    <span v-else class="kg-chip kg-chip--neutral kg-chip--sm">
                        {{ t('inactive') }}
                    </span>
                </template>

                <template #cell-created_at="{ value }">
                    <span class="text-sm text-muted">
                        {{ formatDatetime(value as string | null) }}
                    </span>
                </template>
            </DataTable>
        </div>
    </div>
</template>
