<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import CustomerPartyDetails from '@/Components/Invoices/CustomerPartyDetails.vue';

const props = defineProps<{
    title: string;
    preview: App.Data.Invoices.InvoiceBuyerPreviewResultData | null;
    loading: boolean;
    error: boolean;
}>();

const { t } = useI18n();

const sourceNote = computed<string | null>(() => {
    if (props.preview?.buyer.source === 'object_billing') return t('invoice_buyer_preview_source_object_billing');
    if (props.preview?.buyer.source === 'client') return t('invoice_buyer_preview_source_client');
    return null;
});
</script>

<template>
    <div v-if="loading || error || preview" class="rounded-card-nested bg-base-200 p-4">
        <h3 class="text-sm font-medium text-muted">{{ title }}</h3>
        <span v-if="loading" class="loading loading-spinner loading-sm" />
        <div v-else-if="error" class="alert alert-warning mt-2">
            <span>{{ t('invoice_buyer_preview_failed') }}</span>
        </div>
        <template v-else-if="preview">
            <CustomerPartyDetails :party="preview.buyer" class="mt-2" />
            <p v-if="sourceNote" class="mt-2 text-xs text-muted">{{ sourceNote }}</p>
        </template>
    </div>
</template>
