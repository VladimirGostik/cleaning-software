<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    recurringInvoice: App.Data.RecurringInvoices.RecurringInvoiceDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-base">{{ t('invoice_pdf_customer') }}</h2>

            <a
                v-if="props.recurringInvoice.client_id"
                :href="`/clients/${props.recurringInvoice.client_id}`"
                class="link link-hover font-medium"
            >
                {{ props.recurringInvoice.customer_display_name }}
            </a>
            <p v-else class="font-medium">
                {{ props.recurringInvoice.customer_display_name ?? t('recurring_invoice_no_customer') }}
            </p>

            <p v-if="props.recurringInvoice.customer_representative">
                {{ props.recurringInvoice.customer_representative }}
            </p>
            <p v-if="props.recurringInvoice.customer_ico" class="text-sm text-base-content/60">
                {{ t('client_ico') }}: {{ props.recurringInvoice.customer_ico }}
            </p>
            <p v-if="props.recurringInvoice.customer_dic" class="text-sm text-base-content/60">
                {{ t('client_dic') }}: {{ props.recurringInvoice.customer_dic }}
            </p>
            <p v-if="props.recurringInvoice.customer_vat_number" class="text-sm text-base-content/60">
                {{ t('client_vat_number') }}: {{ props.recurringInvoice.customer_vat_number }}
            </p>
            <p v-if="props.recurringInvoice.customer_street">{{ props.recurringInvoice.customer_street }}</p>
            <p v-if="props.recurringInvoice.customer_postal_code || props.recurringInvoice.customer_city">
                {{
                    [props.recurringInvoice.customer_postal_code, props.recurringInvoice.customer_city]
                        .filter(Boolean)
                        .join(' ')
                }}
            </p>
            <p v-if="props.recurringInvoice.customer_email" class="text-sm text-base-content/60">
                {{ t('email') }}: {{ props.recurringInvoice.customer_email }}
            </p>
        </div>
    </div>
</template>
