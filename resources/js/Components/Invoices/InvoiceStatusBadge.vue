<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { invoiceStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.InvoiceStatusEnum;
    partiallyPaid?: boolean;
}>();

const { t } = useI18n();

const BADGE_CLASS: Record<App.Enums.InvoiceStatusEnum, string> = {
    draft: 'badge-ghost',
    issued: 'badge-primary',
    paid: 'badge-success',
    overdue: 'badge-error',
    cancelled: 'badge-warning',
};

const showPartial = computed(() => props.partiallyPaid === true && props.status !== 'paid');
const badgeClass = computed(() => (showPartial.value ? 'badge-info' : BADGE_CLASS[props.status]));
const label = computed(() =>
    showPartial.value ? t('invoice_status_partially_paid') : t(invoiceStatusKey(props.status)),
);
</script>

<template>
    <span class="badge badge-sm" :class="badgeClass">{{ label }}</span>
</template>
