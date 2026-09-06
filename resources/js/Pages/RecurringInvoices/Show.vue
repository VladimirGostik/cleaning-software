<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import RecurringStatusBadge from '@/Components/RecurringInvoices/RecurringStatusBadge.vue';
import RecurringFrequencyBadge from '@/Components/RecurringInvoices/RecurringFrequencyBadge.vue';
import RecurringCustomerCard from '@/Components/RecurringInvoices/RecurringCustomerCard.vue';
import RecurringScheduleCard from '@/Components/RecurringInvoices/RecurringScheduleCard.vue';
import RecurringGeneratedInvoicesCard from '@/Components/RecurringInvoices/RecurringGeneratedInvoicesCard.vue';
import RecurringActionsCard from '@/Components/RecurringInvoices/RecurringActionsCard.vue';
import InvoiceItemsTable from '@/Components/Invoices/InvoiceItemsTable.vue';
import InvoiceTotalsPanel from '@/Components/Invoices/InvoiceTotalsPanel.vue';

import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    recurringInvoice: App.Data.RecurringInvoices.RecurringInvoiceDetailData;
    generatedInvoices: App.Data.Invoices.InvoiceListItemData[];
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('recurring_invoices'), url: '/recurring-invoices' },
    { label: props.recurringInvoice.name },
]);

const totals = useInvoiceTotals(
    () => props.recurringInvoice.items,
    () => props.recurringInvoice.is_vat_payer,
    () => Number(props.recurringInvoice.deposit),
    () => props.recurringInvoice.rounding_mode,
);

const pauseConfirm = useDeleteConfirm<App.Data.RecurringInvoices.RecurringInvoiceDetailData>({
    method: 'post',
    resolveUrl: (r) => `/recurring-invoices/${r.id}/pause`,
    getTitle: () => t('recurring_invoice_action_pause'),
    getDescription: (r) => t('recurring_invoice_pause_confirm', { name: r.name }),
});

const resumeConfirm = useDeleteConfirm<App.Data.RecurringInvoices.RecurringInvoiceDetailData>({
    method: 'post',
    resolveUrl: (r) => `/recurring-invoices/${r.id}/resume`,
    getTitle: () => t('recurring_invoice_action_resume'),
    getDescription: (r) => t('recurring_invoice_resume_confirm', { name: r.name }),
});

const cancelConfirm = useDeleteConfirm<App.Data.RecurringInvoices.RecurringInvoiceDetailData>({
    method: 'post',
    resolveUrl: (r) => `/recurring-invoices/${r.id}/cancel`,
    getTitle: () => t('recurring_invoice_action_cancel'),
    getDescription: () => t('recurring_invoice_cancel_confirm'),
});

const deleteConfirm = useDeleteConfirm<App.Data.RecurringInvoices.RecurringInvoiceDetailData>({
    method: 'delete',
    resolveUrl: (r) => `/recurring-invoices/${r.id}`,
    getTitle: () => t('delete'),
    getDescription: () => t('recurring_invoice_delete_confirm'),
});
</script>

<template>
    <AppLayout>
        <Header :title="recurringInvoice.name" :breadcrumbs="breadcrumbs">
            <template #actions>
                <RecurringStatusBadge :status="recurringInvoice.status" />
                <RecurringFrequencyBadge :frequency="recurringInvoice.frequency" />
            </template>
        </Header>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
            <div class="space-y-6">
                <RecurringCustomerCard :recurring-invoice="recurringInvoice" />
                <RecurringScheduleCard :recurring-invoice="recurringInvoice" />

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-base">{{ t('invoice_section_items') }}</h2>
                        <InvoiceItemsTable
                            :items="recurringInvoice.items"
                            :currency="recurringInvoice.currency"
                            :is-vat-payer="recurringInvoice.is_vat_payer"
                        />
                        <InvoiceTotalsPanel
                            :subtotal="totals.subtotal.value"
                            :vat-amount="totals.vatAmount.value"
                            :vat-breakdown="totals.vatBreakdown.value"
                            :rounding-amount="totals.roundingAmount.value"
                            :total="totals.total.value"
                            :deposit="Number(recurringInvoice.deposit)"
                            :balance-due="totals.balanceDue.value"
                            :currency="recurringInvoice.currency"
                            :is-vat-payer="recurringInvoice.is_vat_payer"
                        />
                    </div>
                </div>

                <div v-if="recurringInvoice.note" class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('note') }}</h2>
                        <p class="whitespace-pre-wrap text-sm text-base-content/70">{{ recurringInvoice.note }}</p>
                    </div>
                </div>

                <RecurringGeneratedInvoicesCard :invoices="generatedInvoices" />
            </div>

            <div>
                <RecurringActionsCard
                    :recurring-invoice="recurringInvoice"
                    @pause="pauseConfirm.openModal(recurringInvoice)"
                    @resume="resumeConfirm.openModal(recurringInvoice)"
                    @cancel="cancelConfirm.openModal(recurringInvoice)"
                    @delete="deleteConfirm.openModal(recurringInvoice)"
                />
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

        <ConfirmDeleteModal
            :is-open="deleteConfirm.state.isOpen"
            :title="deleteConfirm.getModalTitle()"
            :description="deleteConfirm.getModalDescription()"
            :confirm-label="t('delete')"
            @cancel="deleteConfirm.closeModal"
            @confirm="deleteConfirm.confirmDelete"
        />
    </AppLayout>
</template>
