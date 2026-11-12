<script setup lang="ts">
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { isBefore, parseISO, startOfToday } from 'date-fns';
import Header from '@/Layouts/Header.vue';
import Can from '@/Components/Can.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import QuoteStatusBadge from '@/Components/Quotes/QuoteStatusBadge.vue';
import QuoteKindBadge from '@/Components/Quotes/QuoteKindBadge.vue';
import QuoteRoughBadge from '@/Components/Quotes/QuoteRoughBadge.vue';
import QuotePartiesBlock from '@/Components/Quotes/QuotePartiesBlock.vue';
import QuoteMetaGrid from '@/Components/Quotes/QuoteMetaGrid.vue';
import StatusErrorAlert from '@/Components/StatusErrorAlert.vue';
import QuoteTextDocumentPanel from '@/Components/Quotes/QuoteTextDocumentPanel.vue';
import QuoteDocumentPanel from '@/Components/Quotes/QuoteDocumentPanel.vue';
import QuoteActionsCard from '@/Components/Quotes/QuoteActionsCard.vue';
import QuoteLinksCard from '@/Components/Quotes/QuoteLinksCard.vue';
import QuoteAttachClientPanel from '@/Components/Quotes/QuoteAttachClientPanel.vue';
import QuoteConvertToContractModal from '@/Components/Quotes/QuoteConvertToContractModal.vue';
import QuoteItemsTable from '@/Components/Quotes/QuoteItemsTable.vue';
import QuoteTotalsSplit from '@/Components/Quotes/QuoteTotalsSplit.vue';
import QuoteIncludedList from '@/Components/Quotes/QuoteIncludedList.vue';
import DocumentPdfPreviewModal from '@/Components/Documents/DocumentPdfPreviewModal.vue';

import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { useDocumentPdfPreview } from '@/Composables/useDocumentPdfPreview';
import { getBlob } from '@/utils/http';
import { formatDate } from '@/utils/date';
import { periodTotalsFromData } from '@/utils/quoteBilling';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    quote: App.Data.Quotes.QuoteDetailData;
    clients: App.Data.Clients.ClientOptionData[] | null;
    objects: App.Data.Objects.ObjectOptionData[] | null;
    contractTemplates: App.Data.DocumentTemplates.DocumentTemplateOptionData[];
}>();

const { t } = useI18n();

const pdf = useDocumentPdfPreview({
    load: (signal) => getBlob(`/quotes/${props.quote.id}/preview`, { signal }),
});

const periodTotals = computed(() => periodTotalsFromData(props.quote.period_totals));

const ui = reactive({
    convertContractOpen: false,
});

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
    <Header :title="quote.number ?? t('quote_no_number')" :breadcrumbs="breadcrumbs">
        <template #actions>
            <QuoteStatusBadge :status="quote.status" />
            <QuoteKindBadge :kind="quote.kind" :has-document="quote.document !== null" />
            <QuoteRoughBadge v-if="quote.client_id === null" />
        </template>
    </Header>

    <StatusErrorAlert class="mb-4" />

    <div v-if="isExpiredByDate" class="alert alert-warning mb-4">
        <span>{{ t('quote_expiry_warning', { date: formatDate(quote.valid_until) }) }}</span>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
        <div class="space-y-6">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body space-y-6">
                    <QuotePartiesBlock :quote="quote" />
                    <QuoteMetaGrid :quote="quote" />

                    <template v-if="quote.kind === 'itemized'">
                        <QuoteItemsTable
                            :items="quote.items"
                            :currency="quote.currency"
                            :is-vat-payer="quote.is_vat_payer"
                        />

                        <QuoteTotalsSplit
                            :period-totals="periodTotals"
                            :currency="quote.currency"
                            :is-vat-payer="quote.is_vat_payer"
                        />
                        <p v-if="!quote.show_totals" class="text-xs text-base-content/60">
                            {{ t('quote_totals_not_printed') }}
                        </p>

                        <QuoteIncludedList :text="quote.included_text" />
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
                @convert-contract="ui.convertContractOpen = true"
                @preview="pdf.open"
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

    <QuoteTextDocumentPanel v-if="quote.text_document" class="mt-6" :text-document="quote.text_document" />

    <DocumentPdfPreviewModal
        :is-open="pdf.isOpen.value"
        :pdf-url="pdf.pdfUrl.value"
        :loading="pdf.loading.value"
        :error="pdf.error.value"
        @close="pdf.close"
    />

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

    <QuoteConvertToContractModal
        :open="ui.convertContractOpen"
        :quote-id="quote.id"
        :templates="contractTemplates"
        @close="ui.convertContractOpen = false"
    />
</template>
