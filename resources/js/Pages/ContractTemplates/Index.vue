<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { EyeIcon, PencilSquareIcon, RectangleStackIcon, TrashIcon } from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ContractCategoryBadge from '@/Components/Contracts/ContractCategoryBadge.vue';
import ObjectStatusBadge from '@/Components/Objects/ObjectStatusBadge.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { formatDatetime } from '@/utils/date';
import { CONTRACT_CATEGORIES, contractCategoryKey } from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    templates: Paginator<App.Data.ContractTemplates.ContractTemplateListItemData>;
    filters?: Record<string, unknown>;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('contract_templates') }];

const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(() => props.templates.total === 0 && !hasActiveFilters.value);

const columns: TableColumn<App.Data.ContractTemplates.ContractTemplateListItemData>[] = [
    { key: 'name', label: t('name'), sortable: true },
    { key: 'category', label: t('type'), sortable: true },
    { key: 'is_active', label: t('status'), sortable: true },
    { key: 'updated_at', label: t('updated_at'), sortable: true },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
    {
        property: 'category',
        label: t('type'),
        type: 'select',
        placeholder: t('contract_select_category'),
        defaultOperator: '=',
        options: CONTRACT_CATEGORIES.map((v) => ({ value: v, label: t(contractCategoryKey(v)) })),
    },
    { property: 'is_active', label: t('status'), type: 'boolean', defaultOperator: '=' },
]);

const deleteConfirm = useDeleteConfirm<App.Data.ContractTemplates.ContractTemplateListItemData>({
    method: 'delete',
    resolveUrl: (r) => `/contract-templates/${r.id}`,
    getTitle: () => t('delete'),
    getDescription: (r) => t('contract_template_delete_confirm', { name: r.name }),
});
</script>

<template>
    <AppLayout>
        <Header :title="t('contract_templates')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <a
                    v-if="allows('create contract_templates')"
                    href="/contract-templates/create"
                    class="btn btn-primary btn-sm"
                >
                    {{ t('contract_template_add') }}
                </a>
            </template>
        </Header>

        <EmptyState
            v-if="showEmptyState"
            :title="t('contract_templates_empty')"
            :description="t('contract_templates_empty_hint')"
            :icon="RectangleStackIcon"
        >
            <template #cta>
                <a
                    v-if="allows('create contract_templates')"
                    href="/contract-templates/create"
                    class="btn btn-primary btn-sm"
                >
                    {{ t('contract_template_add') }}
                </a>
            </template>
        </EmptyState>

        <div v-else class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable :columns="columns" :rows="templates" :filters="filterDefinitions">
                    <template #cell-name="{ row }">
                        <a :href="`/contract-templates/${row.id}`" class="link link-hover font-medium">{{
                            row.name
                        }}</a>
                    </template>

                    <template #cell-category="{ row }">
                        <ContractCategoryBadge :category="row.category" />
                    </template>

                    <template #cell-is_active="{ row }">
                        <ObjectStatusBadge :is-active="row.is_active" />
                    </template>

                    <template #cell-updated_at="{ row }">{{ formatDatetime(row.updated_at) }}</template>

                    <template #buttons="{ row }">
                        <a
                            :href="`/contract-templates/${row.id}`"
                            class="btn btn-ghost btn-xs"
                            :title="t('view')"
                            :aria-label="t('view')"
                        >
                            <EyeIcon class="size-4" />
                        </a>

                        <a
                            v-if="allows('edit contract_templates')"
                            :href="`/contract-templates/${row.id}/edit`"
                            class="btn btn-ghost btn-xs"
                            :title="t('edit')"
                            :aria-label="t('edit')"
                        >
                            <PencilSquareIcon class="size-4" />
                        </a>

                        <button
                            v-if="allows('delete contract_templates')"
                            type="button"
                            class="btn btn-ghost btn-xs text-error"
                            :title="t('delete')"
                            :aria-label="t('delete')"
                            @click="deleteConfirm.openModal(row)"
                        >
                            <TrashIcon class="size-4" />
                        </button>
                    </template>
                </DataTable>
            </div>
        </div>

        <ConfirmDeleteModal
            :is-open="deleteConfirm.state.isOpen"
            :title="deleteConfirm.getModalTitle()"
            :description="deleteConfirm.getModalDescription()"
            :confirm-label="t('delete')"
            @cancel="deleteConfirm.closeModal"
            @confirm="deleteConfirm.confirmDelete"
        />
    </AppLayout>
</template>
