<script setup lang="ts">
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
import InvoiceTypeBadge from '@/Components/Invoices/InvoiceTypeBadge.vue';
import InvoiceIssueModal from '@/Components/Invoices/InvoiceIssueModal.vue';
import InvoicePartiesBlock from '@/Components/Invoices/InvoicePartiesBlock.vue';
import InvoiceMetaGrid from '@/Components/Invoices/InvoiceMetaGrid.vue';
import InvoiceItemsTable from '@/Components/Invoices/InvoiceItemsTable.vue';
import InvoiceTotalsPanel from '@/Components/Invoices/InvoiceTotalsPanel.vue';
import InvoicePaymentInfo from '@/Components/Invoices/InvoicePaymentInfo.vue';
import InvoiceActionsCard from '@/Components/Invoices/InvoiceActionsCard.vue';
import InvoiceLinksCard from '@/Components/Invoices/InvoiceLinksCard.vue';
import SupplierIncompleteAlert from '@/Components/Invoices/SupplierIncompleteAlert.vue';
import InvoiceSettingsDrawer from '@/Components/Invoices/InvoiceSettingsDrawer.vue';

import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { useInvoiceSettingsDrawer } from '@/Composables/useInvoiceSettingsDrawer';
import { toNumber } from '@/utils/money';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    invoice: App.Data.Invoices.InvoiceDetailData;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('invoices'), url: '/invoices' },
    { label: props.invoice.number ?? t('invoice_draft_number') },
]);

const ui = reactive({
    issueOpen: false,
});

const payConfirm = useDeleteConfirm<App.Data.Invoices.InvoiceDetailData>({
    method: 'post',
    resolveUrl: (i) => `/invoices/${i.id}/pay`,
    getTitle: () => t('invoice_action_mark_paid'),
    getDescription: () => t('invoice_pay_confirm', { number: props.invoice.number ?? t('invoice_draft_number') }),
});

const cancelConfirm = useDeleteConfirm<App.Data.Invoices.InvoiceDetailData>({
    method: 'post',
    resolveUrl: (i) => `/invoices/${i.id}/cancel`,
    getTitle: () => t('invoice_action_cancel'),
    getDescription: () => t('invoice_cancel_confirm'),
});

const sendConfirm = useDeleteConfirm<App.Data.Invoices.InvoiceDetailData>({
    method: 'post',
    resolveUrl: (i) => `/invoices/${i.id}/send`,
    getTitle: () => t('invoice_action_send_email'),
    getDescription: () => t('invoice_send_confirm', { email: props.invoice.customer_email ?? '' }),
});

const deleteConfirm = useDeleteConfirm<App.Data.Invoices.InvoiceDetailData>({
    method: 'delete',
    resolveUrl: (i) => `/invoices/${i.id}`,
    getTitle: () => t('delete'),
    getDescription: () => t('invoice_delete_confirm'),
});

function duplicate(): void {
    router.post(`/invoices/${props.invoice.id}/duplicate`);
}

const settingsDrawer = useInvoiceSettingsDrawer();

function onSettingsSaved(): void {
    settingsDrawer.close();
    router.reload({ only: ['invoice'] });
}
</script>

<template>
    <AppLayout>
        <Header :title="invoice.number ?? t('invoice_draft_number')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <InvoiceStatusBadge :status="invoice.status" />
                <InvoiceTypeBadge :type="invoice.type" :credit-note="invoice.credited_invoice_id !== null" />
            </template>
        </Header>

        <SupplierIncompleteAlert
            :missing-fields="invoice.supplier_missing_fields"
            @open-settings="settingsDrawer.open"
        />

        <div v-if="invoice.credited_invoice_id" class="alert alert-warning mb-4">
            <span>{{ t('invoice_credit_note_for') }}</span>
            <a :href="`/invoices/${invoice.credited_invoice_id}`" class="link link-hover font-medium">
                {{ t('invoice_view_original') }}
            </a>
        </div>

        <div v-if="invoice.credit_note_id" class="alert alert-info mb-4">
            <span>{{ t('invoice_credit_note_link') }}</span>
            <a :href="`/invoices/${invoice.credit_note_id}`" class="link link-hover font-medium">
                {{ t('invoice_credit_note') }}
            </a>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
            <div class="space-y-6">
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-6">
                        <InvoicePartiesBlock :invoice="invoice" />
                        <InvoiceMetaGrid :invoice="invoice" />

                        <p v-if="invoice.header_text" class="whitespace-pre-wrap text-sm">{{ invoice.header_text }}</p>

                        <InvoiceItemsTable
                            :items="invoice.items"
                            :currency="invoice.currency"
                            :is-vat-payer="invoice.is_vat_payer"
                        />

                        <InvoiceTotalsPanel
                            :subtotal="toNumber(invoice.subtotal)"
                            :vat-amount="toNumber(invoice.vat_amount)"
                            :vat-breakdown="invoice.vat_breakdown"
                            :rounding-amount="toNumber(invoice.rounding_amount)"
                            :total="toNumber(invoice.total)"
                            :deposit="toNumber(invoice.deposit)"
                            :balance-due="toNumber(invoice.balance_due)"
                            :currency="invoice.currency"
                            :is-vat-payer="invoice.is_vat_payer"
                        />

                        <p v-if="invoice.footer_text" class="whitespace-pre-wrap text-sm">{{ invoice.footer_text }}</p>

                        <InvoicePaymentInfo :invoice="invoice" />

                        <p v-if="invoice.note" class="whitespace-pre-wrap text-sm text-base-content/70">
                            {{ invoice.note }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <InvoiceActionsCard
                    :invoice="invoice"
                    @issue="ui.issueOpen = true"
                    @pay="payConfirm.openModal(invoice)"
                    @cancel="cancelConfirm.openModal(invoice)"
                    @send="sendConfirm.openModal(invoice)"
                    @duplicate="duplicate"
                    @delete="deleteConfirm.openModal(invoice)"
                />
                <InvoiceLinksCard :invoice="invoice" />
            </div>
        </div>

        <InvoiceIssueModal :open="ui.issueOpen" :invoice-id="invoice.id" @close="ui.issueOpen = false" />

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

        <ConfirmDeleteModal
            :is-open="sendConfirm.state.isOpen"
            :title="sendConfirm.getModalTitle()"
            :description="sendConfirm.getModalDescription()"
            :confirm-label="t('invoice_action_send_email')"
            confirm-variant="primary"
            @cancel="sendConfirm.closeModal"
            @confirm="sendConfirm.confirmDelete"
        />

        <ConfirmDeleteModal
            :is-open="deleteConfirm.state.isOpen"
            :title="deleteConfirm.getModalTitle()"
            :description="deleteConfirm.getModalDescription()"
            :confirm-label="t('delete')"
            @cancel="deleteConfirm.closeModal"
            @confirm="deleteConfirm.confirmDelete"
        />
        <InvoiceSettingsDrawer
            :open="settingsDrawer.state.isOpen"
            :status="settingsDrawer.state.status"
            :settings="settingsDrawer.state.settings"
            @close="settingsDrawer.close"
            @retry="settingsDrawer.open"
            @saved="onSettingsSaved"
        />
    </AppLayout>
</template>
