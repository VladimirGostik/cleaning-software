<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { BuildingOffice2Icon, EyeIcon, NoSymbolIcon } from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ObjectTypeBadge from '@/Components/Objects/ObjectTypeBadge.vue';
import ObjectStatusBadge from '@/Components/Objects/ObjectStatusBadge.vue';
import ObjectFormDrawer from '@/Components/Objects/ObjectFormDrawer.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { OBJECT_TYPES, objectTypeKey } from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    objects: Paginator<App.Data.Objects.ObjectListItemData>;
    filters?: Record<string, unknown>;
    filterOptions: {
        clients: App.Data.Clients.ClientOptionData[];
    };
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('objects') }];

const ui = reactive({
    createOpen: false,
});

const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(
    () => props.objects.total === 0 && !hasActiveFilters.value && allows('view all objects'),
);

const columns: TableColumn<App.Data.Objects.ObjectListItemData>[] = [
    { key: 'name', label: t('name'), sortable: true },
    { key: 'type', label: t('type'), sortable: true },
    { key: 'client_name', label: t('client'), sortable: false },
    { key: 'city', label: t('city'), sortable: true },
    { key: 'area_sqm', label: t('object_area_sqm'), sortable: false },
    { key: 'is_active', label: t('status'), sortable: true },
];

const filterDefinitions = computed<FilterConfig[]>(() => {
    const definitions: FilterConfig[] = [
        { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
        { property: 'name', label: t('name'), type: 'text', placeholder: t('name'), defaultOperator: '~' },
        {
            property: 'type',
            label: t('type'),
            type: 'select',
            placeholder: t('select_type'),
            defaultOperator: '=',
            options: OBJECT_TYPES.map((v) => ({ value: v, label: t(objectTypeKey(v)) })),
        },
    ];

    if (props.filterOptions.clients.length > 0) {
        definitions.push({
            property: 'client_id',
            label: t('client'),
            type: 'select',
            placeholder: t('select_client'),
            defaultOperator: '=',
            options: props.filterOptions.clients.map((c) => ({ value: c.id, label: c.name })),
        });
    }

    definitions.push(
        { property: 'is_active', label: t('status'), type: 'boolean', defaultOperator: '=' },
        { property: 'city', label: t('city'), type: 'text', placeholder: t('city'), defaultOperator: '~' },
        {
            property: 'created_at',
            label: t('created_at'),
            type: 'date',
            placeholder: t('created_at'),
            defaultOperator: '>=',
            operators: ['>=', '<=', 'between'],
        },
    );

    return definitions;
});

const { state, openModal, closeModal, confirmDelete, getModalTitle, getModalDescription } =
    useDeleteConfirm<App.Data.Objects.ObjectListItemData>({
        method: 'post',
        resolveUrl: (o) => `/objects/${o.id}/deactivate`,
        getTitle: () => t('object_deactivate'),
        getDescription: (o) => `${t('object_deactivate_confirm', { name: o.name })} ${t('object_deactivate_hint')}`,
    });
</script>

<template>
    <AppLayout>
        <Header :title="t('objects')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <button
                    v-if="allows('create objects') && filterOptions.clients.length > 0"
                    type="button"
                    class="btn btn-primary btn-sm"
                    @click="ui.createOpen = true"
                >
                    {{ t('object_add') }}
                </button>
            </template>
        </Header>

        <div v-if="!allows('view all objects')" class="alert alert-info mb-4">
            <span>{{ t('objects_own_only_hint') }}</span>
        </div>

        <EmptyState
            v-if="showEmptyState"
            :title="t('objects_empty')"
            :description="t('objects_empty_hint')"
            :icon="BuildingOffice2Icon"
        >
            <template #cta>
                <button
                    v-if="allows('create objects') && filterOptions.clients.length > 0"
                    type="button"
                    class="btn btn-primary btn-sm"
                    @click="ui.createOpen = true"
                >
                    {{ t('object_add') }}
                </button>
            </template>
        </EmptyState>

        <div v-else class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable :columns="columns" :rows="objects" :filters="filterDefinitions">
                    <template #cell-name="{ row }">
                        <a :href="`/objects/${row.id}`" class="link link-hover font-medium">{{ row.name }}</a>
                    </template>

                    <template #cell-type="{ row }">
                        <ObjectTypeBadge :type="row.type" />
                    </template>

                    <template #cell-client_name="{ row }">
                        <a :href="`/clients/${row.client_id}`" class="link link-hover">
                            {{ row.client_name ?? t('empty_dash') }}
                        </a>
                    </template>

                    <template #cell-area_sqm="{ row }">
                        {{ row.area_sqm !== null ? `${row.area_sqm} m²` : t('empty_dash') }}
                    </template>

                    <template #cell-is_active="{ row }">
                        <ObjectStatusBadge :is-active="row.is_active" />
                    </template>

                    <template #buttons="{ row }">
                        <a :href="`/objects/${row.id}`" class="btn btn-ghost btn-xs" :title="t('view')">
                            <EyeIcon class="size-4" />
                        </a>

                        <button
                            v-if="allows('delete objects') && row.is_active"
                            type="button"
                            class="btn btn-ghost btn-xs text-warning"
                            :title="t('object_deactivate')"
                            @click="openModal(row)"
                        >
                            <NoSymbolIcon class="size-4" />
                        </button>
                    </template>
                </DataTable>
            </div>
        </div>

        <ObjectFormDrawer :open="ui.createOpen" :clients="filterOptions.clients" @close="ui.createOpen = false" />

        <ConfirmDeleteModal
            :is-open="state.isOpen"
            :title="getModalTitle()"
            :description="getModalDescription()"
            :confirm-label="t('object_deactivate')"
            @cancel="closeModal"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
