<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';
import { KG_ICONS } from '@/icons/kegosIcons';
import { invoiceStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.InvoiceStatusEnum;
    partiallyPaid?: boolean;
}>();

const { t } = useI18n();

const TONE: Record<App.Enums.InvoiceStatusEnum, StatusTone> = {
    draft: 'neutral',
    issued: 'planned',
    paid: 'done',
    overdue: 'danger',
    cancelled: 'neutral',
};

const ICON = {
    draft: KG_ICONS.status.draft,
    cancelled: KG_ICONS.status.cancelled,
} as const;

const showPartial = computed(() => props.partiallyPaid === true && props.status !== 'paid');
const tone = computed<StatusTone>(() => (showPartial.value ? 'in_progress' : TONE[props.status]));
const icon = computed(() => (showPartial.value ? undefined : ICON[props.status as keyof typeof ICON]));
const label = computed(() =>
    showPartial.value ? t('invoice_status_partially_paid') : t(invoiceStatusKey(props.status)),
);
</script>

<template>
    <StatusChip :tone="tone" :icon="icon" :label="label" size="sm" />
</template>
