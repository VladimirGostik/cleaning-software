<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { BanknotesIcon, CheckBadgeIcon, DocumentTextIcon, EyeIcon, XCircleIcon } from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
import InvoiceTypeBadge from '@/Components/Invoices/InvoiceTypeBadge.vue';
import InvoiceStatsCards from '@/Components/Invoices/InvoiceStatsCards.vue';
import InvoiceIssueModal from '@/Components/Invoices/InvoiceIssueModal.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { formatDate } from '@/utils/date';
import { INVOICE_STATUSES, INVOICE_TYPES, invoiceStatusKey, invoiceTypeKey } from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();
const { money } = useMoneyFormat();

const props = defineProps<{
    invoices: Paginator<App.Data.Invoices.InvoiceListItemData>;
    filters?: Record<string, unknown>;
    filterOptions: {
        clients: App.Data.Clients.ClientOptionData[];
    };
    stats: App.Data.Invoices.InvoiceStatsData;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('invoices') }];

const ui = reactive({
    issueFor: null as string | null,
});

const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(() => props.invoices.total === 0 && !hasActiveFilters.value);

const columns: TableColumn<App.Data.Invoices.InvoiceListItemData>[] = [
    { key: 'number', label: t('invoice_pdf_number'), sortable: true },
    { key: 'status', label: t('status'), sortable: true },
    { key: 'type', label: t('type'), sortable: true },
    { key: 'customer_name', label: t('client'), sortable: true },
    { key: 'issue_date', label: t('invoice_pdf_issue_date'), sortable: true },
    { key: 'due_date', label: t('invoice_pdf_due_date'), sortable: true },
    { key: 'total', label: t('invoice_pdf_total'), sortable: true, class: 'text-right' },
    { key: 'balance_due', label: t('invoice_pdf_balance_due'), class: 'text-right' },
];

const filterDefinitions = computed<FilterConfig[]>(() => {
    const definitions: FilterConfig[] = [
        { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
        { property: 'number', label: t('invoice_pdf_number'), type: 'text', defaultOperator: '~' },
        {
            property: 'status',
            label: t('status'),
            type: 'select',
            placeholder: t('select_status'),
            defaultOperator: '=',
            options: INVOICE_STATUSES.map((v) => ({ value: v, label: t(invoiceStatusKey(v)) })),
        },
        {
            property: 'type',
            label: t('type'),
            type: 'select',
            placeholder: t('select_type'),
            defaultOperator: '=',
            options: INVOICE_TYPES.map((v) => ({ value: v, label: t(invoiceTypeKey(v)) })),
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
        { property: 'customer_name', label: t('invoice_customer_name'), type: 'text', defaultOperator: '~' },
        {
            property: 'issue_date',
            label: t('invoice_pdf_issue_date'),
            type: 'date',
            defaultOperator: '>=',
            operators: ['>=', '<=', 'between'],
        },
        {
            property: 'due_date',
            label: t('invoice_pdf_due_date'),
            type: 'date',
            defaultOperator: '>=',
            operators: ['>=', '<=', 'between'],
        },
        {
            property: 'total',
            label: t('invoice_pdf_total'),
            type: 'number',
            defaultOperator: '>=',
            operators: ['>=', '<=', 'between', '='],
        },
        { property: 'created_at', label: t('created_at'), type: 'date', defaultOperator: '>=' },
    );

    return definitions;
});

const payConfirm = useDeleteConfirm<App.Data.Invoices.InvoiceListItemData>({
    method: 'post',
    resolveUrl: (r) => `/invoices/${r.id}/pay`,
    getTitle: () => t('invoice_action_mark_paid'),
    getDescription: (r) => t('invoice_pay_confirm', { number: r.number ?? t('invoice_draft_number') }),
});

const cancelConfirm = useDeleteConfirm<App.Data.Invoices.InvoiceListItemData>({
    method: 'post',
    resolveUrl: (r) => `/invoices/${r.id}/cancel`,
    getTitle: () => t('invoice_action_cancel'),
    getDescription: () => t('invoice_cancel_confirm'),
});
</script>

<template>
    <AppLayout>
        <Header :title="t('invoices')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <a v-if="allows('create invoices')" href="/invoices/create" class="btn btn-primary btn-sm">
                    {{ t('invoice_add') }}
                </a>
            </template>
        </Header>

        <InvoiceStatsCards :stats="stats" class="mb-6" />

        <EmptyState
            v-if="showEmptyState"
            :title="t('invoices_empty')"
            :description="t('invoices_empty_hint')"
            :icon="DocumentTextIcon"
        >
            <template #cta>
                <a v-if="allows('create invoices')" href="/invoices/create" class="btn btn-primary btn-sm">
                    {{ t('invoice_add') }}
                </a>
            </template>
        </EmptyState>

        <div v-else class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable :columns="columns" :rows="invoices" :filters="filterDefinitions">
                    <template #cell-number="{ row }">
                        <a :href="`/invoices/${row.id}`" class="link link-hover font-mono font-medium">
                            {{ row.number ?? t('invoice_draft_number') }}
                        </a>
                    </template>

                    <template #cell-status="{ row }">
                        <InvoiceStatusBadge :status="row.status" />
                    </template>

                    <template #cell-type="{ row }">
                        <InvoiceTypeBadge :type="row.type" :credit-note="row.is_credit_note" />
                    </template>

                    <template #cell-customer_name="{ row }">
                        <p>{{ row.customer_name }}</p>
                        <a
                            v-if="row.client_id"
                            :href="`/clients/${row.client_id}`"
                            class="link link-hover text-xs text-base-content/60"
                        >
                            {{ row.client_name }}
                        </a>
                    </template>

                    <template #cell-issue_date="{ row }">{{ formatDate(row.issue_date) }}</template>

                    <template #cell-due_date="{ row }">
                        <span :class="{ 'text-error': row.status === 'overdue' }">{{ formatDate(row.due_date) }}</span>
                    </template>

                    <template #cell-total="{ row }">{{ money(row.total, row.currency) }}</template>

                    <template #cell-balance_due="{ row }">{{ money(row.balance_due, row.currency) }}</template>

                    <template #buttons="{ row }">
                        <a
                            :href="`/invoices/${row.id}`"
                            class="btn btn-ghost btn-xs"
                            :title="t('view')"
                            :aria-label="t('view')"
                        >
                            <EyeIcon class="size-4" />
                        </a>

                        <button
                            v-if="allows('edit invoices') && row.status === 'draft'"
                            type="button"
                            class="btn btn-ghost btn-xs"
                            :title="t('invoice_action_issue')"
                            :aria-label="t('invoice_action_issue')"
                            @click="ui.issueFor = row.id"
                        >
                            <CheckBadgeIcon class="size-4" />
                        </button>

                        <button
                            v-if="allows('edit invoices') && ['issued', 'overdue'].includes(row.status)"
                            type="button"
                            class="btn btn-ghost btn-xs"
                            :title="t('invoice_action_mark_paid')"
                            :aria-label="t('invoice_action_mark_paid')"
                            @click="payConfirm.openModal(row)"
                        >
                            <BanknotesIcon class="size-4" />
                        </button>

                        <button
                            v-if="allows('cancel invoices') && ['issued', 'overdue'].includes(row.status)"
                            type="button"
                            class="btn btn-ghost btn-xs text-warning"
                            :title="t('invoice_action_cancel')"
                            :aria-label="t('invoice_action_cancel')"
                            @click="cancelConfirm.openModal(row)"
                        >
                            <XCircleIcon class="size-4" />
                        </button>
                    </template>
                </DataTable>
            </div>
        </div>

        <InvoiceIssueModal :open="ui.issueFor !== null" :invoice-id="ui.issueFor" @close="ui.issueFor = null" />

        <ConfirmDeleteModal
            :is-open="payConfirm.state.isOpen"
            :title="payConfirm.getModalTitle()"
            :description="payConfirm.getModalDescription()"
            :confirm-label="t('invoice_action_mark_paid')"
            confirm-variant="success"
            @cancel="payConfirm.closeModal"
            @confirm="payConfirm.confirmDelete"
        />

        <ConfirmDeleteModal
            :is-open="cancelConfirm.state.isOpen"
            :title="cancelConfirm.getModalTitle()"
            :description="cancelConfirm.getModalDescription()"
            :confirm-label="t('invoice_action_cancel')"
            confirm-variant="warning"
            @cancel="cancelConfirm.closeModal"
            @confirm="cancelConfirm.confirmDelete"
        />
    </AppLayout>
</template>
