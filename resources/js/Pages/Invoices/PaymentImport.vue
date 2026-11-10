<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import EmptyState from '@/Components/EmptyState.vue';
import BankStatementUploadForm from '@/Components/BankStatement/BankStatementUploadForm.vue';
import InvoicePaymentImportActionBar from '@/Components/Invoices/InvoicePaymentImportActionBar.vue';
import InvoicePaymentImportAssignModal from '@/Components/Invoices/InvoicePaymentImportAssignModal.vue';
import InvoicePaymentImportFilterBar from '@/Components/Invoices/InvoicePaymentImportFilterBar.vue';
import InvoicePaymentImportResultCard from '@/Components/Invoices/InvoicePaymentImportResultCard.vue';
import InvoicePaymentImportSummary from '@/Components/Invoices/InvoicePaymentImportSummary.vue';
import InvoicePaymentImportTable from '@/Components/Invoices/InvoicePaymentImportTable.vue';
import { useInvoicePaymentImport } from '@/Composables/useInvoicePaymentImport';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    limits: App.Data.Invoices.PaymentImportLimitsData;
    result: App.Data.Invoices.PaymentImportResultData | null;
}>();

const { t } = useI18n();
const flow = useInvoicePaymentImport(() => props.limits);
const assigning = ref<string | null>(null);

const assigningRow = computed(() => flow.rows.value.find((row) => row.reference === assigning.value) ?? null);

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('invoices'), url: '/invoices' },
    { label: t('invoice_payment_import_title') },
];

function select(invoice: App.Data.Invoices.PaymentImportInvoiceData): void {
    if (assigning.value) flow.assign(assigning.value, invoice);
    assigning.value = null;
}

function clearAssignment(): void {
    if (assigning.value) flow.unassign(assigning.value);
    assigning.value = null;
}
</script>

<template>
    <Header :title="t('invoice_payment_import_title')" :breadcrumbs="breadcrumbs" />

    <InvoicePaymentImportResultCard v-if="result" :result="result" />

    <div v-else class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <p class="text-sm text-muted">{{ t('invoice_payment_import_intro') }}</p>

            <BankStatementUploadForm
                multiple
                :loading="flow.loading.value"
                :disabled="flow.submitting.value"
                :error="flow.error.value"
                @preview="flow.load"
                @change="flow.clear"
            />

            <template v-if="flow.preview.value">
                <InvoicePaymentImportSummary :preview="flow.preview.value" />

                <EmptyState
                    v-if="!flow.hasMatchedFile.value"
                    :title="t('invoice_payment_import_no_matched_file_title')"
                    :description="t('invoice_payment_import_no_matched_file_description')"
                />

                <EmptyState
                    v-else-if="flow.rows.value.length === 0"
                    :title="t('invoice_payment_import_no_rows_title')"
                    :description="t('invoice_payment_import_no_rows_description')"
                />

                <template v-else>
                    <InvoicePaymentImportFilterBar
                        v-model:filter="flow.filter.value"
                        v-model:search="flow.search.value"
                        :totals="flow.totals.value"
                    />

                    <InvoicePaymentImportTable
                        :rows="flow.visibleRows.value"
                        :projections="flow.projections.value"
                        :disabled="flow.submitting.value"
                        @include="flow.setInclude"
                        @include-visible="flow.setIncludeVisible"
                        @assign="assigning = $event"
                        @unassign="flow.unassign"
                    />

                    <ul v-if="flow.submitErrors.value.length > 0" role="alert" class="text-sm text-error">
                        <li v-for="message in flow.submitErrors.value" :key="message">{{ message }}</li>
                    </ul>

                    <InvoicePaymentImportActionBar
                        :count="flow.selection.value.count"
                        :amount="flow.selection.value.amount"
                        :submitting="flow.submitting.value"
                        @submit="flow.submit"
                    />
                </template>
            </template>
        </div>
    </div>

    <InvoicePaymentImportAssignModal
        :open="assigning !== null"
        :row="assigningRow"
        @close="assigning = null"
        @select="select"
        @clear="clearAssignment"
    />
</template>
