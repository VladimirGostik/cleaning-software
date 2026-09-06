<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { EyeIcon, TrashIcon, UserGroupIcon } from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ClientTypeBadge from '@/Components/Clients/ClientTypeBadge.vue';
import ClientFormDrawer from '@/Components/Clients/ClientFormDrawer.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { CLIENT_TYPES, clientTypeKey } from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    clients: Paginator<App.Data.Clients.ClientListItemData>;
    filters?: Record<string, unknown>;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('clients') }];

const ui = reactive({
    createOpen: false,
});

const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(() => props.clients.total === 0 && !hasActiveFilters.value);

const columns: TableColumn<App.Data.Clients.ClientListItemData>[] = [
    { key: 'name', label: t('name'), sortable: true },
    { key: 'type', label: t('type'), sortable: true },
    { key: 'ico', label: t('client_ico'), sortable: true },
    { key: 'city', label: t('city'), sortable: true },
    { key: 'primary_contact_email', label: t('client_contact_is_primary'), sortable: false },
    { key: 'objects_count', label: t('objects'), sortable: false },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
    { property: 'name', label: t('name'), type: 'text', placeholder: t('name'), defaultOperator: '~' },
    {
        property: 'type',
        label: t('type'),
        type: 'select',
        placeholder: t('select_type'),
        defaultOperator: '=',
        options: CLIENT_TYPES.map((v) => ({ value: v, label: t(clientTypeKey(v)) })),
    },
    { property: 'city', label: t('city'), type: 'text', placeholder: t('city'), defaultOperator: '~' },
    { property: 'ico', label: t('client_ico'), type: 'text', placeholder: t('client_ico'), defaultOperator: '~' },
    {
        property: 'created_at',
        label: t('created_at'),
        type: 'date',
        placeholder: t('created_at'),
        defaultOperator: '>=',
        operators: ['>=', '<=', 'between'],
    },
]);

const { state, openModal, closeModal, confirmDelete, getModalTitle, getModalDescription } =
    useDeleteConfirm<App.Data.Clients.ClientListItemData>({
        resolveUrl: (c) => `/clients/${c.id}`,
        getTitle: () => t('client_delete'),
        getDescription: (c) => `${t('client_delete_confirm', { name: c.name })} ${t('client_delete_cascade_hint')}`,
    });
</script>

<template>
    <AppLayout>
        <Header :title="t('clients')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <button
                    v-if="allows('create clients')"
                    type="button"
                    class="btn btn-primary btn-sm"
                    @click="ui.createOpen = true"
                >
                    {{ t('client_add') }}
                </button>
            </template>
        </Header>

        <EmptyState
            v-if="showEmptyState"
            :title="t('clients_empty')"
            :description="t('clients_empty_hint')"
            :icon="UserGroupIcon"
        >
            <template #cta>
                <button
                    v-if="allows('create clients')"
                    type="button"
                    class="btn btn-primary btn-sm"
                    @click="ui.createOpen = true"
                >
                    {{ t('client_add') }}
                </button>
            </template>
        </EmptyState>

        <div v-else class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable :columns="columns" :rows="clients" :filters="filterDefinitions">
                    <template #cell-name="{ row }">
                        <a :href="`/clients/${row.id}`" class="link link-hover font-medium">{{ row.name }}</a>
                    </template>

                    <template #cell-type="{ row }">
                        <ClientTypeBadge :type="row.type" />
                    </template>

                    <template #cell-ico="{ value }">
                        {{ value ?? t('empty_dash') }}
                    </template>

                    <template #cell-city="{ value }">
                        {{ value ?? t('empty_dash') }}
                    </template>

                    <template #cell-primary_contact_email="{ row }">
                        <div class="text-sm">
                            <div v-if="row.primary_contact_email || row.primary_contact_phone">
                                <div v-if="row.primary_contact_email">{{ row.primary_contact_email }}</div>
                                <div v-if="row.primary_contact_phone">{{ row.primary_contact_phone }}</div>
                            </div>
                            <span v-else>{{ t('empty_dash') }}</span>
                        </div>
                    </template>

                    <template #buttons="{ row }">
                        <a :href="`/clients/${row.id}`" class="btn btn-ghost btn-xs" :title="t('view')">
                            <EyeIcon class="size-4" />
                        </a>

                        <button
                            v-if="allows('delete clients')"
                            type="button"
                            class="btn btn-ghost btn-xs text-error"
                            :title="t('delete')"
                            @click="openModal(row)"
                        >
                            <TrashIcon class="size-4" />
                        </button>
                    </template>
                </DataTable>
            </div>
        </div>

        <ClientFormDrawer :open="ui.createOpen" @close="ui.createOpen = false" />

        <ConfirmDeleteModal
            :is-open="state.isOpen"
            :title="getModalTitle()"
            :description="getModalDescription()"
            @cancel="closeModal"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
