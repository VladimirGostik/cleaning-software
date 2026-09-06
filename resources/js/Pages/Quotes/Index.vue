<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    CheckCircleIcon,
    DocumentDuplicateIcon,
    EyeIcon,
    PaperAirplaneIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import QuoteStatusBadge from '@/Components/Quotes/QuoteStatusBadge.vue';
import QuoteKindBadge from '@/Components/Quotes/QuoteKindBadge.vue';
import QuoteRoughBadge from '@/Components/Quotes/QuoteRoughBadge.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { formatDate } from '@/utils/date';
import { QUOTE_KINDS, QUOTE_STATUSES, quoteKindKey, quoteStatusKey } from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig, TableColumn } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();
const { money } = useMoneyFormat();

const props = defineProps<{
    quotes: Paginator<App.Data.Quotes.QuoteListItemData>;
    filters?: Record<string, unknown>;
    filterOptions: {
        clients: App.Data.Clients.ClientOptionData[];
    };
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('quotes') }];

const hasActiveFilters = computed(() => Object.keys(readSpatieQuery().filters).length > 0);
const showEmptyState = computed(() => props.quotes.total === 0 && !hasActiveFilters.value);

const columns: TableColumn<App.Data.Quotes.QuoteListItemData>[] = [
    { key: 'number', label: t('quote_pdf_number'), sortable: true },
    { key: 'kind', label: t('quote_kind'), sortable: true },
    { key: 'status', label: t('status'), sortable: true },
    { key: 'customer_name', label: t('quote_col_customer'), sortable: false },
    { key: 'object_name', label: t('quote_col_object'), sortable: false },
    { key: 'valid_until', label: t('quote_valid_until'), sortable: true },
    { key: 'total', label: t('invoice_pdf_total'), sortable: true, class: 'text-right' },
];

const filterDefinitions = computed<FilterConfig[]>(() => {
    const definitions: FilterConfig[] = [
        { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
        { property: 'number', label: t('quote_pdf_number'), type: 'text', defaultOperator: '~' },
        {
            property: 'status',
            label: t('status'),
            type: 'select',
            placeholder: t('select_status'),
            defaultOperator: '=',
            options: QUOTE_STATUSES.map((v) => ({ value: v, label: t(quoteStatusKey(v)) })),
        },
        {
            property: 'kind',
            label: t('quote_kind'),
            type: 'select',
            placeholder: t('quote_select_kind'),
            defaultOperator: '=',
            options: QUOTE_KINDS.map((v) => ({ value: v, label: t(quoteKindKey(v)) })),
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
        {
            property: 'issue_date',
            label: t('quote_issue_date'),
            type: 'date',
            defaultOperator: '>=',
            operators: ['>=', '<=', 'between'],
        },
        {
            property: 'valid_until',
            label: t('quote_valid_until'),
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

const sendConfirm = useDeleteConfirm<App.Data.Quotes.QuoteListItemData>({
    method: 'post',
    resolveUrl: (r) => `/quotes/${r.id}/send`,
    getTitle: () => t('quote_action_send'),
    getDescription: (r) => t('quote_send_confirm', { number: r.number ?? t('quote_no_number') }),
});

const acceptConfirm = useDeleteConfirm<App.Data.Quotes.QuoteListItemData>({
    method: 'post',
    resolveUrl: (r) => `/quotes/${r.id}/accept`,
    getTitle: () => t('quote_action_accept'),
    getDescription: (r) => t('quote_accept_confirm', { number: r.number ?? t('quote_no_number') }),
});

const rejectConfirm = useDeleteConfirm<App.Data.Quotes.QuoteListItemData>({
    method: 'post',
    resolveUrl: (r) => `/quotes/${r.id}/reject`,
    getTitle: () => t('quote_action_reject'),
    getDescription: (r) => t('quote_reject_confirm', { number: r.number ?? t('quote_no_number') }),
});
</script>

<template>
    <AppLayout>
        <Header :title="t('quotes')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <a v-if="allows('create quotes')" href="/quotes/create" class="btn btn-primary btn-sm">
                    {{ t('quote_add') }}
                </a>
            </template>
        </Header>

        <EmptyState
            v-if="showEmptyState"
            :title="t('quotes_empty')"
            :description="t('quotes_empty_hint')"
            :icon="DocumentDuplicateIcon"
        >
            <template #cta>
                <a v-if="allows('create quotes')" href="/quotes/create" class="btn btn-primary btn-sm">
                    {{ t('quote_add') }}
                </a>
            </template>
        </EmptyState>

        <div v-else class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable :columns="columns" :rows="quotes" :filters="filterDefinitions">
                    <template #cell-number="{ row }">
                        <a :href="`/quotes/${row.id}`" class="link link-hover font-mono font-medium">
                            {{ row.number ?? t('quote_no_number') }}
                        </a>
                    </template>

                    <template #cell-kind="{ row }">
                        <QuoteKindBadge :kind="row.kind" :has-document="row.has_document" />
                    </template>

                    <template #cell-status="{ row }">
                        <QuoteStatusBadge :status="row.status" />
                    </template>

                    <template #cell-customer_name="{ row }">
                        <p class="flex items-center gap-2">
                            <a v-if="row.client_id" :href="`/clients/${row.client_id}`" class="link link-hover">
                                {{ row.customer_name }}
                            </a>
                            <span v-else>{{ row.customer_name }}</span>
                            <QuoteRoughBadge v-if="row.client_id === null" />
                        </p>
                        <p v-if="row.subject" class="text-xs text-base-content/60">{{ row.subject }}</p>
                    </template>

                    <template #cell-object_name="{ row }">{{ row.object_name ?? t('empty_dash') }}</template>

                    <template #cell-valid_until="{ row }">
                        <span :class="{ 'text-error': row.status === 'expired' }">{{
                            formatDate(row.valid_until)
                        }}</span>
                    </template>

                    <template #cell-total="{ row }">
                        {{ row.kind === 'document' ? t('empty_dash') : money(row.total, row.currency) }}
                    </template>

                    <template #buttons="{ row }">
                        <a
                            :href="`/quotes/${row.id}`"
                            class="btn btn-ghost btn-xs"
                            :title="t('view')"
                            :aria-label="t('view')"
                        >
                            <EyeIcon class="size-4" />
                        </a>

                        <button
                            v-if="allows('send quotes') && row.status === 'draft' && row.kind === 'itemized'"
                            type="button"
                            class="btn btn-ghost btn-xs"
                            :title="t('quote_action_send')"
                            :aria-label="t('quote_action_send')"
                            @click="sendConfirm.openModal(row)"
                        >
                            <PaperAirplaneIcon class="size-4" />
                        </button>

                        <button
                            v-if="allows('approve quotes') && row.status === 'sent' && row.kind === 'itemized'"
                            type="button"
                            class="btn btn-ghost btn-xs"
                            :title="t('quote_action_accept')"
                            :aria-label="t('quote_action_accept')"
                            @click="acceptConfirm.openModal(row)"
                        >
                            <CheckCircleIcon class="size-4" />
                        </button>

                        <button
                            v-if="allows('approve quotes') && row.status === 'sent' && row.kind === 'itemized'"
                            type="button"
                            class="btn btn-ghost btn-xs text-warning"
                            :title="t('quote_action_reject')"
                            :aria-label="t('quote_action_reject')"
                            @click="rejectConfirm.openModal(row)"
                        >
                            <XCircleIcon class="size-4" />
                        </button>
                    </template>
                </DataTable>
            </div>
        </div>

        <ConfirmDeleteModal
            :is-open="sendConfirm.state.isOpen"
            :title="sendConfirm.getModalTitle()"
            :description="sendConfirm.getModalDescription()"
            :confirm-label="t('quote_action_send')"
            confirm-variant="primary"
            @cancel="sendConfirm.closeModal"
            @confirm="sendConfirm.confirmDelete"
        />

        <ConfirmDeleteModal
            :is-open="acceptConfirm.state.isOpen"
            :title="acceptConfirm.getModalTitle()"
            :description="acceptConfirm.getModalDescription()"
            :confirm-label="t('quote_action_accept')"
            confirm-variant="success"
            @cancel="acceptConfirm.closeModal"
            @confirm="acceptConfirm.confirmDelete"
        />

        <ConfirmDeleteModal
            :is-open="rejectConfirm.state.isOpen"
            :title="rejectConfirm.getModalTitle()"
            :description="rejectConfirm.getModalDescription()"
            :confirm-label="t('quote_action_reject')"
            confirm-variant="warning"
            @cancel="rejectConfirm.closeModal"
            @confirm="rejectConfirm.confirmDelete"
        />
    </AppLayout>
</template>
