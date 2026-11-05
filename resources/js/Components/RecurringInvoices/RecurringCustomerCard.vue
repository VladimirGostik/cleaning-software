<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import CustomerPartyDetails from '@/Components/Invoices/CustomerPartyDetails.vue';
import { useAuthorization } from '@/Composables/useAuthorization';

const props = defineProps<{
    recurringInvoice: App.Data.RecurringInvoices.RecurringInvoiceDetailData;
}>();

const { t } = useI18n();
const { allows } = useAuthorization();

const buyer = computed(() => props.recurringInvoice.buyer);

const sourceNote = computed(() => {
    if (buyer.value.source === 'object_billing') {
        return t('recurring_invoice_buyer_source_object_billing', {
            object: props.recurringInvoice.cleaning_object_name ?? '',
        });
    }
    if (buyer.value.source === 'client') {
        return t('recurring_invoice_buyer_source_client', {
            client: buyer.value.represented_by ?? buyer.value.name ?? '',
        });
    }
    return t('recurring_invoice_buyer_source_entered');
});

const editLink = computed<{ href: string; label: string } | null>(() => {
    const ri = props.recurringInvoice;
    if (buyer.value.source === 'object_billing') {
        if (ri.cleaning_object_id && allows('edit objects') && allows('view all objects')) {
            return {
                href: `/objects/${ri.cleaning_object_id}?edit=billing`,
                label: t('recurring_invoice_edit_object_billing'),
            };
        }
        return null;
    }
    if (buyer.value.source === 'client') {
        if (ri.client_id && allows('edit clients')) {
            return { href: `/clients/${ri.client_id}`, label: t('recurring_invoice_edit_client') };
        }
        return null;
    }
    if (allows('edit recurring_invoices') && (ri.status === 'active' || ri.status === 'paused')) {
        return { href: `/recurring-invoices/${ri.id}/edit`, label: t('recurring_invoice_edit_buyer') };
    }
    return null;
});
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-base">{{ t('invoice_pdf_customer') }}</h2>

            <p v-if="!buyer.name" class="font-medium">{{ t('recurring_invoice_no_customer') }}</p>
            <CustomerPartyDetails :party="buyer" />

            <p class="text-xs text-base-content/60">{{ sourceNote }}</p>

            <p v-if="!buyer.email" class="text-sm text-base-content/60">{{ t('recurring_invoice_email_missing') }}</p>
            <div v-if="!buyer.email && recurringInvoice.auto_send" role="alert" class="alert alert-warning">
                <span>{{ t('recurring_invoice_email_missing_auto_send') }}</span>
            </div>

            <div v-if="editLink" class="card-actions">
                <Link :href="editLink.href" class="btn btn-neutral btn-sm">{{ editLink.label }}</Link>
            </div>
        </div>
    </div>
</template>
