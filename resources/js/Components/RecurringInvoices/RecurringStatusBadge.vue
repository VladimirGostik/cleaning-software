<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { recurringStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.RecurringInvoiceStatusEnum;
}>();

const { t } = useI18n();

const BADGE_CLASS: Record<App.Enums.RecurringInvoiceStatusEnum, string> = {
    active: 'badge-success',
    paused: 'badge-warning',
    completed: 'badge-ghost',
    cancelled: 'badge-error',
};

const badgeClass = computed(() => BADGE_CLASS[props.status]);
</script>

<template>
    <span class="badge badge-sm" :class="badgeClass">{{ t(recurringStatusKey(status)) }}</span>
</template>
