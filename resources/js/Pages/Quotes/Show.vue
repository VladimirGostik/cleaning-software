<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { isBefore, parseISO, startOfToday } from 'date-fns';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import Can from '@/Components/Can.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import QuoteStatusBadge from '@/Components/Quotes/QuoteStatusBadge.vue';
import QuoteKindBadge from '@/Components/Quotes/QuoteKindBadge.vue';
import QuoteRoughBadge from '@/Components/Quotes/QuoteRoughBadge.vue';
import QuotePartiesBlock from '@/Components/Quotes/QuotePartiesBlock.vue';
import QuoteMetaGrid from '@/Components/Quotes/QuoteMetaGrid.vue';
import QuoteDocumentPanel from '@/Components/Quotes/QuoteDocumentPanel.vue';
import QuoteActionsCard from '@/Components/Quotes/QuoteActionsCard.vue';
import QuoteLinksCard from '@/Components/Quotes/QuoteLinksCard.vue';
import QuoteAttachClientPanel from '@/Components/Quotes/QuoteAttachClientPanel.vue';
import InvoiceItemsTable from '@/Components/Invoices/InvoiceItemsTable.vue';
import InvoiceTotalsPanel from '@/Components/Invoices/InvoiceTotalsPanel.vue';

import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { formatDate } from '@/utils/date';
import { toNumber } from '@/utils/money';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    quote: App.Data.Quotes.QuoteDetailData;
    clients: App.Data.Clients.ClientOptionData[] | null;
    objects: App.Data.Objects.ObjectOptionData[] | null;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('quotes'), url: '/quotes' },
    { label: props.quote.number ?? t('quote_no_number') },
]);

const isExpiredByDate = computed(
    () =>
        props.quote.kind === 'itemized' &&
        ['draft', 'sent'].includes(props.quote.status) &&
        isBefore(parseISO(props.quote.valid_until), startOfToday()),
);

const sendConfirm = useDeleteConfirm<App.Data.Quotes.QuoteDetailData>({
    method: 'post',
    resolveUrl: (q) => `/quotes/${q.id}/send`,
    getTitle: () => t('quote_action_send'),
    getDescription: () => t('quote_send_confirm', { number: props.quote.number ?? t('quote_no_number') }),
});

const acceptConfirm = useDeleteConfirm<App.Data.Quotes.QuoteDetailData>({
    method: 'post',
    resolveUrl: (q) => `/quotes/${q.id}/accept`,
    getTitle: () => t('quote_action_accept'),
    getDescription: () => t('quote_accept_confirm', { number: props.quote.number ?? t('quote_no_number') }),
});

const rejectConfirm = useDeleteConfirm<App.Data.Quotes.QuoteDetailData>({
    method: 'post',
    resolveUrl: (q) => `/quotes/${q.id}/reject`,
    getTitle: () => t('quote_action_reject'),
    getDescription: () => t('quote_reject_confirm', { number: props.quote.number ?? t('quote_no_number') }),
});

const deleteConfirm = useDeleteConfirm<App.Data.Quotes.QuoteDetailData>({
    method: 'delete',
    resolveUrl: (q) => `/quotes/${q.id}`,
    getTitle: () => t('delete'),
    getDescription: () => t('quote_delete_confirm'),
});

const convertConfirm = useDeleteConfirm<App.Data.Quotes.QuoteDetailData>({
    method: 'post',
    resolveUrl: (q) => `/quotes/${q.id}/convert-to-invoice`,
    getTitle: () => t('quote_action_convert_invoice'),
    getDescription: () => t('quote_convert_again_confirm', { count: props.quote.invoices.length }),
});

function onConvertInvoice(): void {
    if (props.quote.invoices.length > 0) {
        convertConfirm.openModal(props.quote);
    } else {
        router.post(`/quotes/${props.quote.id}/convert-to-invoice`);
    }
}

function duplicate(): void {
    router.post(`/quotes/${props.quote.id}/duplicate`);
}
</script>

<template>
    <AppLayout>
        <Header :title="quote.number ?? t('quote_no_number')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <QuoteStatusBadge :status="quote.status" />
                <QuoteKindBadge :kind="quote.kind" :has-document="quote.document !== null" />
                <QuoteRoughBadge v-if="quote.client_id === null" />
            </template>
        </Header>

        <div v-if="isExpiredByDate" class="alert alert-warning mb-4">
            <span>{{ t('quote_expiry_warning', { date: formatDate(quote.valid_until) }) }}</span>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
            <div class="space-y-6">
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-6">
                        <QuotePartiesBlock :quote="quote" />
                        <QuoteMetaGrid :quote="quote" />

                        <template v-if="quote.kind === 'itemized'">
                            <InvoiceItemsTable
                                :items="quote.items"
                                :currency="quote.currency"
                                :is-vat-payer="quote.is_vat_payer"
                            />

                            <InvoiceTotalsPanel
                                :subtotal="toNumber(quote.subtotal)"
                                :vat-amount="toNumber(quote.vat_amount)"
                                :vat-breakdown="quote.vat_breakdown"
                                :rounding-amount="0"
                                :total="toNumber(quote.total)"
                                :deposit="0"
                                :balance-due="toNumber(quote.total)"
                                :currency="quote.currency"
                                :is-vat-payer="quote.is_vat_payer"
                            />
                        </template>

                        <QuoteDocumentPanel v-else :quote="quote" />

                        <p v-if="quote.note" class="whitespace-pre-wrap text-sm text-base-content/70">
                            {{ quote.note }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <QuoteActionsCard
                    :quote="quote"
                    @send="sendConfirm.openModal(quote)"
                    @accept="acceptConfirm.openModal(quote)"
                    @reject="rejectConfirm.openModal(quote)"
                    @duplicate="duplicate"
                    @delete="deleteConfirm.openModal(quote)"
                    @convert-invoice="onConvertInvoice"
                />
                <QuoteLinksCard :quote="quote" />

                <Can permission="edit quotes">
                    <QuoteAttachClientPanel
                        v-if="quote.client_id === null && clients"
                        :quote-id="quote.id"
                        :clients="clients"
                        :objects="objects ?? []"
                    />
                </Can>
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

        <ConfirmDeleteModal
            :is-open="deleteConfirm.state.isOpen"
            :title="deleteConfirm.getModalTitle()"
            :description="deleteConfirm.getModalDescription()"
            :confirm-label="t('delete')"
            @cancel="deleteConfirm.closeModal"
            @confirm="deleteConfirm.confirmDelete"
        />

        <ConfirmDeleteModal
            :is-open="convertConfirm.state.isOpen"
            :title="convertConfirm.getModalTitle()"
            :description="convertConfirm.getModalDescription()"
            :confirm-label="t('quote_action_convert_invoice')"
            confirm-variant="primary"
            @cancel="convertConfirm.closeModal"
            @confirm="convertConfirm.confirmDelete"
        />
    </AppLayout>
</template>
