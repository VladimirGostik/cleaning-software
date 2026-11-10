<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
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
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { formatDate, formatMonth } from '@/utils/date';
import {
    EXPENSE_CATEGORIES,
    EXPENSE_SOURCES,
    EXPENSE_STATUSES,
    expenseCategoryKey,
    expenseSourceKey,
    expenseStatusKey,
} from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn, TableTabsConfig } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();
const { money } = useMoneyFormat();

const props = defineProps<{
    expenses: Paginator<App.Data.Expenses.ExpenseListItemData>;
    filters?: Record<string, unknown>;
    context: App.Data.Expenses.ExpenseFormContextData;
    totals: App.Data.Expenses.ExpenseTotalData[];
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('expenses') }];

const ui = reactive({
    createOpen: false,
});

const canCreate = computed(() => props.context.companies.length > 0);
const showTabs = computed(() => props.context.employees.length > 0);
const undocumentedTab = ref(readSpatieQuery().filters.undocumented === '1');
watch(
    () => props.expenses,
    () => {
        undocumentedTab.value = readSpatieQuery().filters.undocumented === '1';
    },
);
const isUndocumented = computed(() => showTabs.value && undocumentedTab.value);

const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(() => props.expenses.total === 0 && !hasActiveFilters.value && !showTabs.value);

const tabs = computed<TableTabsConfig | undefined>(() =>
    showTabs.value
        ? {
              property: 'undocumented',
              defaultTab: '0',
              options: [
                  { value: '0', label: t('expense_cash_tab_documented') },
                  { value: '1', label: t('expense_cash_tab_undocumented') },
              ],
          }
        : undefined,
);

const documentedColumns = computed<TableColumn<App.Data.Expenses.ExpenseListItemData>[]>(() => [
    { key: 'date', label: t('expense_date'), sortable: true, nowrap: true },
    { key: 'supplier_name', label: t('expense_supplier'), sortable: true },
    { key: 'amount', label: t('expense_amount'), sortable: true, align: 'right', nowrap: true },
    { key: 'category', label: t('expense_category'), sortable: false },
    { key: 'status', label: t('status'), sortable: true },
    { key: 'source', label: t('expense_source'), sortable: false },
    { key: 'tenant_name', label: t('expense_company'), sortable: false },
]);

const undocumentedColumns = computed<TableColumn<App.Data.Expenses.ExpenseListItemData>[]>(() => [
    { key: 'date', label: t('expense_cash_date'), sortable: true, nowrap: true },
    { key: 'employee_name', label: t('expense_cash_employee'), sortable: false },
    { key: 'amount', label: t('expense_cash_amount'), sortable: true, align: 'right', nowrap: true },
    { key: 'description', label: t('expense_cash_note'), sortable: false },
    { key: 'tenant_name', label: t('expense_company'), sortable: false },
]);

const columns = computed(() => (isUndocumented.value ? undocumentedColumns.value : documentedColumns.value));

function payeeLabel(row: App.Data.Expenses.ExpenseListItemData): string {
    if (!row.employee_name) return row.supplier_name ?? t('empty_dash');

    return row.payroll_month ? `${row.employee_name} · ${formatMonth(row.payroll_month)}` : row.employee_name;
}

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
                {{ isUndocumented ? t('expense_cash_add') : t('expense_add') }}
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
            <p v-if="totals.length > 0" class="flex flex-wrap items-baseline gap-x-4 gap-y-1 text-sm">
                <span class="text-base-content/60">{{ t('expense_cash_totals') }}:</span>
                <span v-for="total in totals" :key="total.currency" class="font-semibold tabular-nums">
                    {{ money(total.amount, total.currency) }} · {{ total.count }}
                </span>
            </p>

            <DataTable
                :columns="columns"
                :rows="expenses"
                :filters="filterDefinitions"
                :tabs="tabs"
                :row-href="(row) => `/expenses/${row.id}`"
            >
                <template #card="{ row }">
                    <Link :href="`/expenses/${row.id}`" class="block space-y-1">
                        <span class="flex items-start justify-between gap-3">
                            <span class="min-w-0 font-medium">{{ payeeLabel(row) }}</span>
                            <span class="shrink-0 font-semibold tabular-nums">{{
                                money(row.amount, row.currency)
                            }}</span>
                        </span>
                        <span class="flex flex-wrap items-center gap-2 text-sm text-base-content/70">
                            <span>{{ formatDate(row.date) }}</span>
                            <ExpenseCategoryBadge
                                v-if="row.category && !row.is_undocumented"
                                :category="row.category"
                            />
                            <ExpenseStatusBadge v-if="!row.is_undocumented" :status="row.status" />
                        </span>
                        <span v-if="row.is_undocumented && row.description" class="block text-sm text-base-content/70">
                            {{ row.description }}
                        </span>
                    </Link>
                </template>

                <template #cell-date="{ row }">
                    <Link :href="`/expenses/${row.id}`" class="link link-hover font-medium">
                        {{ formatDate(row.date) }}
                    </Link>
                </template>

                <template #cell-supplier_name="{ row }">{{ payeeLabel(row) }}</template>

                <template #cell-employee_name="{ row }">{{ row.employee_name ?? t('empty_dash') }}</template>

                <template #cell-description="{ row }">
                    <span class="line-clamp-2">{{ row.description ?? t('empty_dash') }}</span>
                </template>

                <template #cell-amount="{ row }">{{ money(row.amount, row.currency) }}</template>

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

    <ExpenseFormDrawer
        :open="ui.createOpen"
        :context="context"
        :undocumented="isUndocumented"
        @close="ui.createOpen = false"
    />
</template>
