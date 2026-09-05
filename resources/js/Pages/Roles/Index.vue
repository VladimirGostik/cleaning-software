<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import type { Paginator, Breadcrumb, TableColumn } from '@/types';

const { t } = useI18n();
const page = usePage();

defineProps<{
    roles: Paginator<App.Data.RoleListItemData>;
    filters?: Record<string, unknown>;
}>();

const can = page.props.can;

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('roles') },
];

const columns: TableColumn[] = [
    { key: 'name', label: t('name'), sortable: true },
    { key: 'permissions_count', label: t('permissions') },
    { key: 'users_count', label: t('users') },
    { key: 'is_system', label: '' },
];
</script>

<template>
    <AppLayout>
        <Header :title="t('roles')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <a v-if="can.createRoles" href="/roles/create" class="btn btn-primary btn-sm">
                    {{ t('create') }}
                </a>
            </template>
        </Header>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable
                    :columns="columns"
                    :rows="roles"
                    :can-edit="!!can.editRoles"
                    :can-delete="!!can.deleteRoles"
                    :edit-url="(row: App.Data.RoleListItemData) => `/roles/${row.id}/edit`"
                    :delete-url="(row: App.Data.RoleListItemData) => `/roles/${row.id}`"
                    :can-delete-row="(row: App.Data.RoleListItemData) => !row.is_system"
                >
                    <template #cell-is_system="{ row }">
                        <span
                            v-if="(row as App.Data.RoleListItemData).is_system"
                            class="badge badge-warning badge-sm"
                        >
                            system
                        </span>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
