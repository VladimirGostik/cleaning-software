<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    BanknotesIcon,
    CheckBadgeIcon,
    DocumentDuplicateIcon,
    DocumentTextIcon,
    EyeIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
import InvoiceTypeBadge from '@/Components/Invoices/InvoiceTypeBadge.vue';
import InvoiceStatsCards from '@/Components/Invoices/InvoiceStatsCards.vue';
import InvoicePayModal from '@/Components/Invoices/InvoicePayModal.vue';
import InvoiceBulkActionsBar from '@/Components/Invoices/InvoiceBulkActionsBar.vue';
import InvoiceBulkResultModal from '@/Components/Invoices/InvoiceBulkResultModal.vue';
import InvoiceIssueModal from '@/Components/Invoices/InvoiceIssueModal.vue';
import InvoicePohodaExportModal from '@/Components/Invoices/InvoicePohodaExportModal.vue';
import InvoiceSettingsDrawer from '@/Components/Invoices/InvoiceSettingsDrawer.vue';
import Can from '@/Components/Can.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useInvoiceSettingsDrawer } from '@/Composables/useInvoiceSettingsDrawer';
import { usePageProps } from '@/Composables/usePageProps';
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
    bulkLimits: App.Data.Invoices.InvoiceBulkLimitsData;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('invoices') }];

const pageProps = usePageProps();
const selectedIds = ref<string[]>([]);
const bulkResult = ref<App.Data.Invoices.BulkActionResultData | null>(null);

watch(
    () => pageProps.value.flash?.bulk_result ?? null,
    (result) => {
        if (result) bulkResult.value = result;
    },
    { immediate: true },
);

function payLabel(creditNote: boolean): string {
    return t(creditNote ? 'invoice_action_record_refund' : 'invoice_action_record_payment');
}

function duplicate(id: string): void {
    router.post(`/invoices/${id}/duplicate`);
}

const ui = reactive({
    issueFor: null as string | null,
    payFor: null as App.Data.Invoices.InvoiceListItemData | null,
    pohodaOpen: false,
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

const cancelConfirm = useDeleteConfirm<App.Data.Invoices.InvoiceListItemData>({
    method: 'post',
    resolveUrl: (r) => `/invoices/${r.id}/cancel`,
    getTitle: () => t('invoice_action_cancel'),
    getDescription: () => t('invoice_cancel_confirm'),
});

const settingsDrawer = useInvoiceSettingsDrawer();

function onSettingsSaved(): void {
    settingsDrawer.close();
}
</script>

<template>
    <Header :title="t('invoices')" :breadcrumbs="breadcrumbs">
        <template #actions>
            <button
                v-if="allows('view invoices')"
                type="button"
                class="btn btn-ghost btn-sm"
                @click="ui.pohodaOpen = true"
            >
                {{ t('pohoda_export_button') }}
            </button>
            <Can permission="manage billing settings">
                <button type="button" class="btn btn-ghost btn-sm" @click="settingsDrawer.open">
                    {{ t('invoicing_settings') }}
                </button>
            </Can>
            <Link v-if="allows('create invoices')" href="/invoices/create" class="btn btn-primary btn-sm">
                {{ t('invoice_add') }}
            </Link>
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
            <Link v-if="allows('create invoices')" href="/invoices/create" class="btn btn-primary btn-sm">
                {{ t('invoice_add') }}
            </Link>
        </template>
    </EmptyState>

    <div v-else class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <InvoiceBulkActionsBar
                v-if="selectedIds.length"
                :ids="selectedIds"
                :limits="bulkLimits"
                @clear="selectedIds = []"
            />

            <DataTable
                v-model:selected="selectedIds"
                selectable
                row-key="id"
                :select-label="
                    (row) => t('invoice_bulk_select_row', { number: row.number ?? t('invoice_draft_number') })
                "
                :select-all-label="t('invoice_bulk_select_all')"
                :columns="columns"
                :rows="invoices"
                :filters="filterDefinitions"
                :row-href="(row) => `/invoices/${row.id}`"
            >
                <template #cell-number="{ row }">
                    <Link :href="`/invoices/${row.id}`" class="link link-hover font-mono font-medium">
                        {{ row.number ?? t('invoice_draft_number') }}
                    </Link>
                </template>

                <template #cell-status="{ row }">
                    <InvoiceStatusBadge :status="row.status" :partially-paid="row.is_partially_paid" />
                </template>

                <template #cell-type="{ row }">
                    <InvoiceTypeBadge :type="row.type" :credit-note="row.is_credit_note" />
                </template>

                <template #cell-customer_name="{ row }">
                    <p>{{ row.customer_name }}</p>
                    <Link
                        v-if="row.client_id"
                        :href="`/clients/${row.client_id}`"
                        class="link link-hover text-xs text-base-content/60"
                    >
                        {{ row.client_name }}
                    </Link>
                </template>

                <template #cell-issue_date="{ row }">{{ formatDate(row.issue_date) }}</template>

                <template #cell-due_date="{ row }">
                    <span :class="{ 'text-error': row.status === 'overdue' }">{{ formatDate(row.due_date) }}</span>
                </template>

                <template #cell-total="{ row }">{{ money(row.total, row.currency) }}</template>

                <template #cell-balance_due="{ row }">{{ money(row.balance_due, row.currency) }}</template>

                <template #buttons="{ row }">
                    <Link
                        :href="`/invoices/${row.id}`"
                        class="btn btn-ghost btn-xs"
                        :title="t('view')"
                        :aria-label="t('view')"
                    >
                        <EyeIcon class="size-4" />
                    </Link>

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
                        :title="payLabel(row.is_credit_note)"
                        :aria-label="payLabel(row.is_credit_note)"
                        @click="ui.payFor = row"
                    >
                        <BanknotesIcon class="size-4" />
                    </button>

                    <button
                        v-if="allows('create invoices')"
                        type="button"
                        class="btn btn-ghost btn-xs"
                        :title="t('invoice_action_duplicate')"
                        :aria-label="t('invoice_action_duplicate')"
                        @click="duplicate(row.id)"
                    >
                        <DocumentDuplicateIcon class="size-4" />
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

    <InvoicePohodaExportModal :open="ui.pohodaOpen" @close="ui.pohodaOpen = false" />

    <InvoiceBulkResultModal :open="bulkResult !== null" :result="bulkResult" @close="bulkResult = null" />

    <InvoiceSettingsDrawer
        :open="settingsDrawer.state.isOpen"
        :status="settingsDrawer.state.status"
        :settings="settingsDrawer.state.settings"
        :signature="settingsDrawer.state.signature"
        :constraints="settingsDrawer.state.constraints"
        :mail-placeholders="settingsDrawer.state.mailPlaceholders"
        @close="settingsDrawer.close"
        @retry="settingsDrawer.open"
        @saved="onSettingsSaved"
    />

    <InvoicePayModal
        :open="ui.payFor !== null"
        :invoice-id="ui.payFor?.id ?? null"
        :balance-due="ui.payFor?.balance_due ?? null"
        :credit-note="ui.payFor?.is_credit_note ?? false"
        :payment-type="ui.payFor?.payment_type ?? 'transfer'"
        @close="ui.payFor = null"
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
</template>
