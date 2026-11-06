<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { countryName } from '@/utils/country';

const props = defineProps<{
    party: App.Data.Invoices.InvoiceBuyerData;
}>();

const { t, te } = useI18n();

const cityLine = computed(() => [props.party.postal_code, props.party.city].filter(Boolean).join(' '));
</script>

<template>
    <div>
        <p v-if="party.name" class="font-medium">{{ party.name }}</p>
        <p v-if="party.represented_by" class="text-sm text-base-content/60">
            {{ t('invoice_pdf_represented_by') }}: {{ party.represented_by }}
        </p>
        <p v-if="party.representative">{{ party.representative }}</p>
        <p v-if="party.street">{{ party.street }}</p>
        <p v-if="cityLine">{{ cityLine }}</p>
        <p v-if="party.country">{{ countryName(party.country, { t, te }) }}</p>
        <p v-if="party.ico" class="text-sm text-base-content/60">{{ t('invoice_pdf_ico') }}: {{ party.ico }}</p>
        <p v-if="party.dic" class="text-sm text-base-content/60">{{ t('invoice_pdf_dic') }}: {{ party.dic }}</p>
        <p v-if="party.vat_number" class="text-sm text-base-content/60">
            {{ t('invoice_pdf_vat_number') }}: {{ party.vat_number }}
        </p>
        <p v-if="party.email" class="text-sm text-base-content/60">{{ t('invoice_pdf_email') }}: {{ party.email }}</p>
    </div>
</template>
