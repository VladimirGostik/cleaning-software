<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { invoiceTypeKey } from '@/utils/enums';

const props = withDefaults(
    defineProps<{
        type: App.Enums.InvoiceTypeEnum;
        creditNote?: boolean;
    }>(),
    { creditNote: false },
);

const { t } = useI18n();
</script>

<template>
    <StatusChip v-if="props.creditNote" tone="warning" :label="t('invoice_credit_note')" size="sm" />
    <StatusChip v-else tone="neutral" :label="t(invoiceTypeKey(props.type))" size="sm" />
</template>
