<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { formatDate } from '@/utils/date';

const props = defineProps<{
    invoices: readonly App.Data.Invoices.InvoiceListItemData[];
}>();

const { t } = useI18n();
const { money } = useMoneyFormat();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-base">{{ t('recurring_invoice_generated_invoices') }}</h2>

            <EmptyState v-if="props.invoices.length === 0" :title="t('recurring_invoice_no_generated_invoices')" />

            <table v-else class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ t('invoice_pdf_number') }}</th>
                        <th>{{ t('status') }}</th>
                        <th>{{ t('invoice_pdf_issue_date') }}</th>
                        <th class="text-right">{{ t('invoice_pdf_total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="invoice in props.invoices" :key="invoice.id">
                        <td>
                            <a :href="`/invoices/${invoice.id}`" class="link link-hover font-mono">
                                {{ invoice.number ?? t('invoice_draft_number') }}
                            </a>
                        </td>
                        <td><InvoiceStatusBadge :status="invoice.status" /></td>
                        <td>{{ formatDate(invoice.issue_date) }}</td>
                        <td class="text-right">{{ money(invoice.total, invoice.currency) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
