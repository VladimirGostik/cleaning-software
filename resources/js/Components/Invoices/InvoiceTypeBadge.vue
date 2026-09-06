<script setup lang="ts">
import { useI18n } from 'vue-i18n';
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
    <span v-if="props.creditNote" class="badge badge-sm badge-warning badge-outline">
        {{ t('invoice_credit_note') }}
    </span>
    <span v-else class="badge badge-sm badge-ghost">{{ t(invoiceTypeKey(props.type)) }}</span>
</template>
