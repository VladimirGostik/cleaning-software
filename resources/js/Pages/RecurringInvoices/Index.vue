<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowPathIcon, CheckIcon, EyeIcon, PauseIcon, PlayIcon, XCircleIcon } from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import RecurringStatusBadge from '@/Components/RecurringInvoices/RecurringStatusBadge.vue';
import RecurringFrequencyBadge from '@/Components/RecurringInvoices/RecurringFrequencyBadge.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { formatDate } from '@/utils/date';
import { RECURRING_FREQUENCIES, RECURRING_STATUSES, recurringFrequencyKey, recurringStatusKey } from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();

const props = defineProps<{
    recurringInvoices: Paginator<App.Data.RecurringInvoices.RecurringInvoiceListItemData>;
    filters?: Record<string, unknown>;
    filterOptions: {
        clients: App.Data.Clients.ClientOptionData[];
    };
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('recurring_invoices') }];

const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(() => props.recurringInvoices.total === 0 && !hasActiveFilters.value);

const columns: TableColumn<App.Data.RecurringInvoices.RecurringInvoiceListItemData>[] = [
    { key: 'name', label: t('recurring_invoice_name'), sortable: true },
    { key: 'frequency', label: t('recurring_invoice_frequency'), sortable: true },
    { key: 'status', label: t('status'), sortable: true },
    { key: 'next_run_at', label: t('recurring_invoice_next_run'), sortable: true },
    { key: 'occurrences', label: t('recurring_invoice_occurrences'), sortable: false },
    { key: 'auto_issue', label: t('recurring_invoice_auto_issue'), sortable: false },
    { key: 'start_date', label: t('recurring_invoice_start_date'), sortable: false },
];

const filterDefinitions = computed<FilterConfig[]>(() => {
    const definitions: FilterConfig[] = [
        { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
        {
            property: 'status',
            label: t('status'),
            type: 'select',
            placeholder: t('select_status'),
            defaultOperator: '=',
            options: RECURRING_STATUSES.map((v) => ({ value: v, label: t(recurringStatusKey(v)) })),
        },
        {
            property: 'frequency',
            label: t('recurring_invoice_frequency'),
            type: 'select',
            placeholder: t('select_type'),
            defaultOperator: '=',
            options: RECURRING_FREQUENCIES.map((v) => ({ value: v, label: t(recurringFrequencyKey(v)) })),
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
        { property: 'next_run_at', label: t('recurring_invoice_next_run'), type: 'date', defaultOperator: '>=' },
        { property: 'created_at', label: t('created_at'), type: 'date', defaultOperator: '>=' },
    );

    return definitions;
});

const pauseConfirm = useDeleteConfirm<App.Data.RecurringInvoices.RecurringInvoiceListItemData>({
    method: 'post',
    resolveUrl: (r) => `/recurring-invoices/${r.id}/pause`,
    getTitle: () => t('recurring_invoice_action_pause'),
    getDescription: (r) => t('recurring_invoice_pause_confirm', { name: r.name }),
});

const resumeConfirm = useDeleteConfirm<App.Data.RecurringInvoices.RecurringInvoiceListItemData>({
    method: 'post',
    resolveUrl: (r) => `/recurring-invoices/${r.id}/resume`,
    getTitle: () => t('recurring_invoice_action_resume'),
    getDescription: (r) => t('recurring_invoice_resume_confirm', { name: r.name }),
});

const cancelConfirm = useDeleteConfirm<App.Data.RecurringInvoices.RecurringInvoiceListItemData>({
    method: 'post',
    resolveUrl: (r) => `/recurring-invoices/${r.id}/cancel`,
    getTitle: () => t('recurring_invoice_action_cancel'),
    getDescription: () => t('recurring_invoice_cancel_confirm'),
});
</script>

<template>
    <AppLayout>
        <Header :title="t('recurring_invoices')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <a
                    v-if="allows('create recurring_invoices')"
                    href="/recurring-invoices/create"
                    class="btn btn-primary btn-sm"
                >
                    {{ t('recurring_invoice_add') }}
                </a>
            </template>
        </Header>

        <EmptyState
            v-if="showEmptyState"
            :title="t('recurring_invoices_empty')"
            :description="t('recurring_invoices_empty_hint')"
            :icon="ArrowPathIcon"
        >
            <template #cta>
                <a
                    v-if="allows('create recurring_invoices')"
                    href="/recurring-invoices/create"
                    class="btn btn-primary btn-sm"
                >
                    {{ t('recurring_invoice_add') }}
                </a>
            </template>
        </EmptyState>

        <div v-else class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable :columns="columns" :rows="recurringInvoices" :filters="filterDefinitions">
                    <template #cell-name="{ row }">
                        <a :href="`/recurring-invoices/${row.id}`" class="link link-hover font-medium">{{
                            row.name
                        }}</a>
                        <p class="text-xs text-base-content/60">
                            {{ row.customer_display_name ?? t('recurring_invoice_no_customer') }}
                        </p>
                    </template>

                    <template #cell-frequency="{ row }"
                        ><RecurringFrequencyBadge :frequency="row.frequency"
                    /></template>
                    <template #cell-status="{ row }"><RecurringStatusBadge :status="row.status" /></template>
                    <template #cell-next_run_at="{ row }">{{ formatDate(row.next_run_at) }}</template>

                    <template #cell-occurrences="{ row }">
                        {{ row.occurrences_generated }}/{{ row.occurrences_limit ?? '∞' }}
                    </template>

                    <template #cell-auto_issue="{ row }">
                        <CheckIcon v-if="row.auto_issue" class="size-4 text-success" />
                        <span v-else>{{ t('empty_dash') }}</span>
                    </template>

                    <template #cell-start_date="{ row }">{{ formatDate(row.start_date) }}</template>

                    <template #buttons="{ row }">
                        <a
                            :href="`/recurring-invoices/${row.id}`"
                            class="btn btn-ghost btn-xs"
                            :title="t('view')"
                            :aria-label="t('view')"
                        >
                            <EyeIcon class="size-4" />
                        </a>

                        <button
                            v-if="allows('edit recurring_invoices') && row.status === 'active'"
                            type="button"
                            class="btn btn-ghost btn-xs"
                            :title="t('recurring_invoice_action_pause')"
                            :aria-label="t('recurring_invoice_action_pause')"
                            @click="pauseConfirm.openModal(row)"
                        >
                            <PauseIcon class="size-4" />
                        </button>

                        <button
                            v-if="allows('edit recurring_invoices') && row.status === 'paused'"
                            type="button"
                            class="btn btn-ghost btn-xs"
                            :title="t('recurring_invoice_action_resume')"
                            :aria-label="t('recurring_invoice_action_resume')"
                            @click="resumeConfirm.openModal(row)"
                        >
                            <PlayIcon class="size-4" />
                        </button>

                        <button
                            v-if="allows('delete recurring_invoices') && ['active', 'paused'].includes(row.status)"
                            type="button"
                            class="btn btn-ghost btn-xs text-warning"
                            :title="t('recurring_invoice_action_cancel')"
                            :aria-label="t('recurring_invoice_action_cancel')"
                            @click="cancelConfirm.openModal(row)"
                        >
                            <XCircleIcon class="size-4" />
                        </button>
                    </template>
                </DataTable>
            </div>
        </div>

        <ConfirmDeleteModal
            :is-open="pauseConfirm.state.isOpen"
            :title="pauseConfirm.getModalTitle()"
            :description="pauseConfirm.getModalDescription()"
            :confirm-label="t('recurring_invoice_action_pause')"
            confirm-variant="warning"
            @cancel="pauseConfirm.closeModal"
            @confirm="pauseConfirm.confirmDelete"
        />

        <ConfirmDeleteModal
            :is-open="resumeConfirm.state.isOpen"
            :title="resumeConfirm.getModalTitle()"
            :description="resumeConfirm.getModalDescription()"
            :confirm-label="t('recurring_invoice_action_resume')"
            confirm-variant="success"
            @cancel="resumeConfirm.closeModal"
            @confirm="resumeConfirm.confirmDelete"
        />

        <ConfirmDeleteModal
            :is-open="cancelConfirm.state.isOpen"
            :title="cancelConfirm.getModalTitle()"
            :description="cancelConfirm.getModalDescription()"
            :confirm-label="t('recurring_invoice_action_cancel')"
            confirm-variant="warning"
            @cancel="cancelConfirm.closeModal"
            @confirm="cancelConfirm.confirmDelete"
        />
    </AppLayout>
</template>
