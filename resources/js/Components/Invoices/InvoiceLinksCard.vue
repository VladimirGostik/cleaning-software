<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { formatDatetime } from '@/utils/date';

const props = defineProps<{
    invoice: App.Data.Invoices.InvoiceDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-3 text-sm">
            <h2 class="card-title text-base">{{ t('invoice_section_links') }}</h2>

            <p v-if="props.invoice.client_id">
                <span class="text-base-content/60">{{ t('invoice_link_client') }}:</span>
                <Link :href="`/clients/${props.invoice.client_id}`" class="link link-hover ml-1">
                    {{ props.invoice.client_name }}
                </Link>
            </p>

            <p v-if="props.invoice.object_name">
                <span class="text-base-content/60">{{ t('invoice_link_object') }}:</span>
                <span class="ml-1">{{ props.invoice.object_name }}</span>
            </p>

            <p v-if="props.invoice.recurring_invoice_id">
                <span class="text-base-content/60">{{ t('invoice_link_recurring') }}:</span>
                <Link :href="`/recurring-invoices/${props.invoice.recurring_invoice_id}`" class="link link-hover ml-1">
                    {{ t('invoice_link_recurring') }}
                </Link>
            </p>

            <p v-if="props.invoice.quote_id">
                <span class="text-base-content/60">{{ t('invoice_link_quote') }}:</span>
                <Link :href="`/quotes/${props.invoice.quote_id}`" class="link link-hover ml-1">
                    {{ props.invoice.quote_number ?? t('quote_no_number') }}
                </Link>
            </p>

            <p v-if="props.invoice.credited_invoice_id">
                <span class="text-base-content/60">{{ t('invoice_credit_note_for') }}:</span>
                <Link :href="`/invoices/${props.invoice.credited_invoice_id}`" class="link link-hover ml-1">
                    {{ t('invoice_view_original') }}
                </Link>
            </p>

            <p v-if="props.invoice.credit_note_id">
                <span class="text-base-content/60">{{ t('invoice_credit_note_link') }}:</span>
                <Link :href="`/invoices/${props.invoice.credit_note_id}`" class="link link-hover ml-1">
                    {{ t('invoice_credit_note') }}
                </Link>
            </p>

            <div class="divider my-1" />

            <ul class="space-y-1 text-base-content/60">
                <li v-if="props.invoice.issued_at">
                    {{ t('invoice_issued_at') }}: {{ formatDatetime(props.invoice.issued_at) }}
                </li>
                <li>
                    {{ props.invoice.sent_at ? t('invoice_sent_at') : t('invoice_not_sent') }}
                    <template v-if="props.invoice.sent_at">: {{ formatDatetime(props.invoice.sent_at) }}</template>
                </li>
                <li v-if="props.invoice.paid_at">
                    {{ t('invoice_paid_at') }}: {{ formatDatetime(props.invoice.paid_at) }}
                </li>
                <li v-if="props.invoice.cancelled_at">
                    {{ t('invoice_cancelled_at') }}: {{ formatDatetime(props.invoice.cancelled_at) }}
                </li>
            </ul>
        </div>
    </div>
</template>
