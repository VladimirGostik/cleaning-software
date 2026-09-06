<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { invoiceStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.InvoiceStatusEnum;
}>();

const { t } = useI18n();

const BADGE_CLASS: Record<App.Enums.InvoiceStatusEnum, string> = {
    draft: 'badge-ghost',
    issued: 'badge-primary',
    paid: 'badge-success',
    overdue: 'badge-error',
    cancelled: 'badge-warning',
};

const badgeClass = computed(() => BADGE_CLASS[props.status]);
</script>

<template>
    <span class="badge badge-sm" :class="badgeClass">{{ t(invoiceStatusKey(status)) }}</span>
</template>
