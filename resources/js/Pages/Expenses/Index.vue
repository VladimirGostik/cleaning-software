<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import BanknotesIcon from '~icons/material-symbols/receipt-long-outline-rounded';
import EyeIcon from '~icons/material-symbols/visibility-outline-rounded';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ExpenseStatusBadge from '@/Components/Expenses/ExpenseStatusBadge.vue';
import ExpenseCategoryBadge from '@/Components/Expenses/ExpenseCategoryBadge.vue';
import ExpenseFormDrawer from '@/Components/Expenses/ExpenseFormDrawer.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { formatDate } from '@/utils/date';
import {
    EXPENSE_CATEGORIES,
    EXPENSE_SOURCES,
    EXPENSE_STATUSES,
    expenseCategoryKey,
    expenseSourceKey,
    expenseStatusKey,
} from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    expenses: Paginator<App.Data.Expenses.ExpenseListItemData>;
    filters?: Record<string, unknown>;
    context: App.Data.Expenses.ExpenseFormContextData;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('expenses') }];

const ui = reactive({
    createOpen: false,
});

const canCreate = computed(() => props.context.companies.length > 0);
const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(() => props.expenses.total === 0 && !hasActiveFilters.value);

const columns: TableColumn<App.Data.Expenses.ExpenseListItemData>[] = [
    { key: 'date', label: t('expense_date'), sortable: true, nowrap: true },
    { key: 'supplier_name', label: t('expense_supplier'), sortable: true },
    { key: 'amount', label: t('expense_amount'), sortable: true, align: 'right' },
    { key: 'category', label: t('expense_category'), sortable: false },
    { key: 'status', label: t('status'), sortable: true },
    { key: 'source', label: t('expense_source'), sortable: false },
    { key: 'tenant_name', label: t('expense_company'), sortable: false },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
    {
        property: 'status',
        label: t('status'),
        type: 'select',
        placeholder: t('status'),
        defaultOperator: '=',
        options: EXPENSE_STATUSES.map((v) => ({ value: v, label: t(expenseStatusKey(v)) })),
    },
    {
        property: 'category',
        label: t('expense_category'),
        type: 'select',
        placeholder: t('expense_category'),
        defaultOperator: '=',
        options: EXPENSE_CATEGORIES.map((v) => ({ value: v, label: t(expenseCategoryKey(v)) })),
    },
    {
        property: 'source',
        label: t('expense_source'),
        type: 'select',
        placeholder: t('expense_source'),
        defaultOperator: '=',
        options: EXPENSE_SOURCES.map((v) => ({ value: v, label: t(expenseSourceKey(v)) })),
    },
    ...(props.context.companies.length > 1
        ? [
              {
                  property: 'tenant_id',
                  label: t('expense_company'),
                  type: 'select' as const,
                  placeholder: t('expense_company'),
                  defaultOperator: '=' as const,
                  options: props.context.companies.map((c) => ({ value: c.tenant_id, label: c.name })),
              },
          ]
        : []),
    {
        property: 'date',
        label: t('expense_date'),
        type: 'date',
        placeholder: t('expense_date'),
        defaultOperator: '>=',
        operators: ['>=', '<=', 'between'],
    },
]);
</script>

<template>
    <Header :title="t('expenses')" :breadcrumbs="breadcrumbs">
        <template #actions>
            <Link v-if="canCreate && allows('manage expenses')" href="/expenses/import" class="btn btn-neutral btn-sm">
                {{ t('expense_import') }}
            </Link>
            <button v-if="canCreate" type="button" class="btn btn-secondary btn-sm" @click="ui.createOpen = true">
                {{ t('expense_add') }}
            </button>
        </template>
    </Header>

    <EmptyState
        v-if="showEmptyState"
        :title="t('expenses_empty')"
        :description="t('expenses_empty_hint')"
        :icon="BanknotesIcon"
    >
        <template #cta>
            <button v-if="canCreate" type="button" class="btn btn-primary btn-sm" @click="ui.createOpen = true">
                {{ t('expense_add') }}
            </button>
        </template>
    </EmptyState>

    <div v-else class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <DataTable
                :columns="columns"
                :rows="expenses"
                :filters="filterDefinitions"
                :row-href="(row) => `/expenses/${row.id}`"
            >
                <template #cell-date="{ row }">
                    <Link :href="`/expenses/${row.id}`" class="link link-hover font-medium">
                        {{ formatDate(row.date) }}
                    </Link>
                </template>

                <template #cell-supplier_name="{ row }">
                    {{ row.supplier_name ?? t('empty_dash') }}
                </template>

                <template #cell-amount="{ row }">{{ row.amount }} {{ row.currency }}</template>

                <template #cell-category="{ row }">
                    <ExpenseCategoryBadge v-if="row.category" :category="row.category" />
                    <span v-else>{{ t('empty_dash') }}</span>
                </template>

                <template #cell-status="{ row }">
                    <ExpenseStatusBadge :status="row.status" />
                </template>

                <template #cell-source="{ row }">{{ t(expenseSourceKey(row.source)) }}</template>

                <template #cell-tenant_name="{ row }">
                    {{ row.tenant_name ?? t('empty_dash') }}
                </template>

                <template #buttons="{ row }">
                    <Link :href="`/expenses/${row.id}`" class="btn btn-ghost btn-xs" :title="t('view')">
                        <EyeIcon class="size-5" />
                    </Link>
                </template>
            </DataTable>
        </div>
    </div>

    <ExpenseFormDrawer :open="ui.createOpen" :context="context" @close="ui.createOpen = false" />
</template>
