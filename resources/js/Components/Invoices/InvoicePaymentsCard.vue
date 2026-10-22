<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { TrashIcon } from '@heroicons/vue/24/outline';

import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { formatDate } from '@/utils/date';
import { paymentTypeKey } from '@/utils/enums';

const props = defineProps<{
    invoice: App.Data.Invoices.InvoiceDetailData;
}>();

const { t } = useI18n();
const { allows } = useAuthorization();
const { money } = useMoneyFormat();

const canDelete = computed(() => allows('edit invoices') && props.invoice.status !== 'cancelled');

const deleteConfirm = useDeleteConfirm<App.Data.Invoices.InvoicePaymentListItemData>({
    resolveUrl: (p) => `/invoices/${props.invoice.id}/payments/${p.id}`,
    getTitle: () => t('invoice_payment_delete_title'),
    getDescription: () => t('invoice_payment_delete_confirm'),
});
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-3">
            <h2 class="card-title text-base">{{ t('invoice_payments_title') }}</h2>

            <dl class="grid grid-cols-2 gap-1 text-sm">
                <dt class="text-base-content/60">{{ t('invoice_paid_amount') }}</dt>
                <dd class="text-right font-medium">{{ money(invoice.paid_amount, invoice.currency) }}</dd>
                <dt class="text-base-content/60">{{ t('invoice_balance_due_label') }}</dt>
                <dd class="text-right font-medium">{{ money(invoice.balance_due, invoice.currency) }}</dd>
            </dl>

            <p v-if="invoice.payments.length === 0" class="text-sm text-base-content/60">
                {{ t('invoice_payments_empty') }}
            </p>

            <ul v-else class="divide-y divide-base-200">
                <li v-for="payment in invoice.payments" :key="payment.id" class="flex items-start gap-2 py-2 text-sm">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ money(payment.amount, invoice.currency) }}</p>
                        <p class="text-xs text-base-content/60">
                            {{ formatDate(payment.paid_on) }} · {{ t(paymentTypeKey(payment.payment_type)) }} ·
                            {{ t(`invoice_payment_source_${payment.source}`) }}
                        </p>
                        <p v-if="payment.recorded_by_name" class="text-xs text-base-content/60">
                            {{ t('invoice_payment_recorded_by') }}: {{ payment.recorded_by_name }}
                        </p>
                        <p v-if="payment.note" class="whitespace-pre-wrap text-xs text-base-content/70">
                            {{ payment.note }}
                        </p>
                    </div>
                    <button
                        v-if="canDelete"
                        type="button"
                        class="btn btn-ghost btn-xs text-error"
                        :title="t('delete')"
                        :aria-label="t('delete')"
                        @click="deleteConfirm.openModal(payment)"
                    >
                        <TrashIcon class="size-4" />
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <ConfirmDeleteModal
        :is-open="deleteConfirm.state.isOpen"
        :title="deleteConfirm.getModalTitle()"
        :description="deleteConfirm.getModalDescription()"
        :confirm-label="t('delete')"
        @cancel="deleteConfirm.closeModal"
        @confirm="deleteConfirm.confirmDelete"
    />
</template>
