<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    invoice: App.Data.Invoices.InvoiceDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <h3 class="text-sm font-semibold text-base-content/70">{{ t('invoice_pdf_supplier') }}</h3>
            <p class="font-medium">{{ props.invoice.supplier.name }}</p>
            <p v-if="props.invoice.supplier.address_line">{{ props.invoice.supplier.address_line }}</p>
            <p v-if="props.invoice.supplier.postal_code || props.invoice.supplier.city">
                {{ [props.invoice.supplier.postal_code, props.invoice.supplier.city].filter(Boolean).join(' ') }}
            </p>
            <p v-if="props.invoice.supplier.country">{{ props.invoice.supplier.country }}</p>
            <p v-if="props.invoice.supplier.ico" class="text-sm text-base-content/60">
                {{ t('invoice_pdf_ico') }}: {{ props.invoice.supplier.ico }}
            </p>
            <p v-if="props.invoice.supplier.dic" class="text-sm text-base-content/60">
                {{ t('invoice_pdf_dic') }}: {{ props.invoice.supplier.dic }}
            </p>
            <p v-if="props.invoice.supplier.vat_number" class="text-sm text-base-content/60">
                {{ t('invoice_pdf_vat_number') }}: {{ props.invoice.supplier.vat_number }}
            </p>
            <p v-if="props.invoice.supplier.contact_email" class="text-sm text-base-content/60">
                {{ t('invoice_pdf_email') }}: {{ props.invoice.supplier.contact_email }}
            </p>
            <p v-if="props.invoice.supplier.contact_phone" class="text-sm text-base-content/60">
                {{ t('invoice_pdf_phone') }}: {{ props.invoice.supplier.contact_phone }}
            </p>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-base-content/70">{{ t('invoice_pdf_customer') }}</h3>
            <p class="font-medium">{{ props.invoice.customer_name }}</p>
            <p v-if="props.invoice.customer_representative">{{ props.invoice.customer_representative }}</p>
            <p v-if="props.invoice.customer_street">{{ props.invoice.customer_street }}</p>
            <p v-if="props.invoice.customer_postal_code || props.invoice.customer_city">
                {{ [props.invoice.customer_postal_code, props.invoice.customer_city].filter(Boolean).join(' ') }}
            </p>
            <p v-if="props.invoice.customer_country">{{ props.invoice.customer_country }}</p>
            <p v-if="props.invoice.customer_ico" class="text-sm text-base-content/60">
                {{ t('invoice_pdf_ico') }}: {{ props.invoice.customer_ico }}
            </p>
            <p v-if="props.invoice.customer_dic" class="text-sm text-base-content/60">
                {{ t('invoice_pdf_dic') }}: {{ props.invoice.customer_dic }}
            </p>
            <p v-if="props.invoice.customer_vat_number" class="text-sm text-base-content/60">
                {{ t('invoice_pdf_vat_number') }}: {{ props.invoice.customer_vat_number }}
            </p>
            <p v-if="props.invoice.customer_email" class="text-sm text-base-content/60">
                {{ t('invoice_pdf_email') }}: {{ props.invoice.customer_email }}
            </p>
        </div>

        <img
            v-if="props.invoice.qr_data_uri"
            :src="props.invoice.qr_data_uri"
            :alt="t('invoice_payment_qr')"
            class="size-24"
        />
    </div>
</template>
