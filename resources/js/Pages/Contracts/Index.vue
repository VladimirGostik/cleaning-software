<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { CheckCircleIcon, DocumentCheckIcon, EyeIcon, XCircleIcon } from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ContractStatusBadge from '@/Components/Contracts/ContractStatusBadge.vue';
import ContractCategoryBadge from '@/Components/Contracts/ContractCategoryBadge.vue';
import ContractTermBadge from '@/Components/Contracts/ContractTermBadge.vue';
import ContractTerminateModal from '@/Components/Contracts/ContractTerminateModal.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { formatDate } from '@/utils/date';
import {
    CONTRACT_CATEGORIES,
    CONTRACT_STATUSES,
    CONTRACT_TERM_TYPES,
    CONTRACTABLE_TYPES,
    contractableTypeKey,
    contractCategoryKey,
    contractStatusKey,
    contractTermTypeKey,
} from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    contracts: Paginator<App.Data.Contracts.ContractListItemData>;
    filters?: Record<string, unknown>;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('contracts') }];

const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(() => props.contracts.total === 0 && !hasActiveFilters.value);

const ui = reactive({
    terminateFor: null as string | null,
});

const columns: TableColumn<App.Data.Contracts.ContractListItemData>[] = [
    { key: 'title', label: t('name'), sortable: true },
    { key: 'contractable_label', label: t('contract_contractable'), sortable: false },
    { key: 'category', label: t('type'), sortable: true },
    { key: 'status', label: t('status'), sortable: true },
    { key: 'term_type', label: t('contract_term_type'), sortable: false },
    { key: 'valid_from', label: t('contract_valid_from'), sortable: true },
    { key: 'end_date', label: t('contract_end_date'), sortable: true },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
    {
        property: 'status',
        label: t('status'),
        type: 'select',
        placeholder: t('select_status'),
        defaultOperator: '=',
        options: CONTRACT_STATUSES.map((v) => ({ value: v, label: t(contractStatusKey(v)) })),
    },
    {
        property: 'category',
        label: t('type'),
        type: 'select',
        placeholder: t('contract_select_category'),
        defaultOperator: '=',
        options: CONTRACT_CATEGORIES.map((v) => ({ value: v, label: t(contractCategoryKey(v)) })),
    },
    {
        property: 'term_type',
        label: t('contract_term_type'),
        type: 'select',
        placeholder: t('contract_select_term_type'),
        defaultOperator: '=',
        options: CONTRACT_TERM_TYPES.map((v) => ({ value: v, label: t(contractTermTypeKey(v)) })),
    },
    {
        property: 'contractable_type',
        label: t('contract_contractable'),
        type: 'select',
        placeholder: t('contract_select_contractable_type'),
        defaultOperator: '=',
        options: CONTRACTABLE_TYPES.map((v) => ({ value: v, label: t(contractableTypeKey(v)) })),
    },
    {
        property: 'valid_from',
        label: t('contract_valid_from'),
        type: 'date',
        defaultOperator: '>=',
        operators: ['>=', '<=', 'between'],
    },
    {
        property: 'end_date',
        label: t('contract_end_date'),
        type: 'date',
        defaultOperator: '>=',
        operators: ['>=', '<=', 'between'],
    },
]);

const signConfirm = useDeleteConfirm<App.Data.Contracts.ContractListItemData>({
    method: 'post',
    resolveUrl: (r) => `/contracts/${r.id}/sign`,
    getTitle: () => t('contract_action_sign'),
    getDescription: (r) => t('contract_sign_confirm', { title: r.title }),
});
</script>

<template>
    <AppLayout>
        <Header :title="t('contracts')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <a v-if="allows('create contracts')" href="/contracts/create" class="btn btn-primary btn-sm">
                    {{ t('contract_add') }}
                </a>
            </template>
        </Header>

        <EmptyState
            v-if="showEmptyState"
            :title="t('contracts_empty')"
            :description="t('contracts_empty_hint')"
            :icon="DocumentCheckIcon"
        >
            <template #cta>
                <a v-if="allows('create contracts')" href="/contracts/create" class="btn btn-primary btn-sm">
                    {{ t('contract_add') }}
                </a>
            </template>
        </EmptyState>

        <div v-else class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable :columns="columns" :rows="contracts" :filters="filterDefinitions">
                    <template #cell-title="{ row }">
                        <a :href="`/contracts/${row.id}`" class="link link-hover font-medium">{{ row.title }}</a>
                        <p v-if="row.number" class="font-mono text-xs text-base-content/60">{{ row.number }}</p>
                    </template>

                    <template #cell-contractable_label="{ row }">
                        {{ row.contractable_label }}
                        <p class="text-xs text-base-content/60">{{ t(contractableTypeKey(row.contractable_type)) }}</p>
                    </template>

                    <template #cell-category="{ row }">
                        <ContractCategoryBadge :category="row.category" />
                    </template>

                    <template #cell-status="{ row }">
                        <ContractStatusBadge :status="row.status" />
                    </template>

                    <template #cell-term_type="{ row }">
                        <ContractTermBadge :term-type="row.term_type" />
                    </template>

                    <template #cell-valid_from="{ row }">{{ formatDate(row.valid_from) }}</template>

                    <template #cell-end_date="{ row }">
                        <span :class="{ 'text-error': row.status === 'expired' }">
                            {{ row.end_date ? formatDate(row.end_date) : t('contract_end_date_indefinite') }}
                        </span>
                    </template>

                    <template #buttons="{ row }">
                        <a
                            :href="`/contracts/${row.id}`"
                            class="btn btn-ghost btn-xs"
                            :title="t('view')"
                            :aria-label="t('view')"
                        >
                            <EyeIcon class="size-4" />
                        </a>

                        <button
                            v-if="allows('edit contracts') && row.can_be_signed"
                            type="button"
                            class="btn btn-ghost btn-xs"
                            :title="t('contract_action_sign')"
                            :aria-label="t('contract_action_sign')"
                            @click="signConfirm.openModal(row)"
                        >
                            <CheckCircleIcon class="size-4" />
                        </button>

                        <button
                            v-if="allows('terminate contracts') && row.can_be_terminated"
                            type="button"
                            class="btn btn-ghost btn-xs text-warning"
                            :title="t('contract_action_terminate')"
                            :aria-label="t('contract_action_terminate')"
                            @click="ui.terminateFor = row.id"
                        >
                            <XCircleIcon class="size-4" />
                        </button>
                    </template>
                </DataTable>
            </div>
        </div>

        <ConfirmDeleteModal
            :is-open="signConfirm.state.isOpen"
            :title="signConfirm.getModalTitle()"
            :description="signConfirm.getModalDescription()"
            :confirm-label="t('contract_action_sign')"
            confirm-variant="success"
            @cancel="signConfirm.closeModal"
            @confirm="signConfirm.confirmDelete"
        />

        <ContractTerminateModal
            :open="ui.terminateFor !== null"
            :contract-id="ui.terminateFor"
            @close="ui.terminateFor = null"
        />
    </AppLayout>
</template>
