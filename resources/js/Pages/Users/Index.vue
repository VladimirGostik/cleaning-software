<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';

import { formatDatetime } from '@/utils/date';
import type { Breadcrumb, Paginator, TableColumn } from '@/types';
import type { FilterConfig } from '@/types/table';

const { t } = useI18n();
const page = usePage();

const props = defineProps<{
    users: Paginator<App.Data.UserListItemData>;
    query?: Record<string, unknown>;
    filterOptions: {
        roles: App.Data.RoleListItemData[];
    };
}>();

const can = page.props.can as Record<string, boolean>;

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('users') }];

const columns: TableColumn[] = [
    { key: 'name', label: t('name'), sortable: true },
    { key: 'email', label: t('email'), sortable: true },
    { key: 'roles', label: t('roles'), sortable: false },
    { key: 'is_active', label: t('is_active'), sortable: true },
    { key: 'created_at', label: t('created_at'), sortable: true },
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
        label: t('is_active'),
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
    <AppLayout>
        <Header :title="t('users')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <a v-if="can.createUsers" href="/users/create" class="btn btn-primary btn-sm">
                    {{ t('create') }}
                </a>
            </template>
        </Header>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable
                    :columns="columns"
                    :rows="users"
                    :filters="filterDefinitions"
                    route-name="users.index"
                    :can-edit="!!can.editUsers"
                    :can-delete="!!can.deleteUsers"
                    :edit-url="(row: App.Data.UserListItemData) => `/users/${row.id}/edit`"
                    :delete-url="(row: App.Data.UserListItemData) => `/users/${row.id}`"
                >
                    <template #cell-roles="{ row }">
                        <div class="flex flex-wrap gap-1">
                            <span v-for="role in row.roles" :key="role" class="badge badge-ghost badge-sm">
                                {{ role }}
                            </span>

                            <span v-if="row.roles.length === 0" class="text-base-content/40 text-sm">
                                {{ t('no_roles') }}
                            </span>
                        </div>
                    </template>

                    <template #cell-is_active="{ value }">
                        <span v-if="value" class="badge badge-success badge-sm">
                            {{ t('active') }}
                        </span>
                        <span v-else class="badge badge-ghost badge-sm">
                            {{ t('inactive') }}
                        </span>
                    </template>

                    <template #cell-created_at="{ value }">
                        <span class="text-sm text-base-content/70">
                            {{ formatDatetime(value as string | null) }}
                        </span>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
