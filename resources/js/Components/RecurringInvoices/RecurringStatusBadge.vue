<script setup lang="ts">
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';
import { KG_ICONS } from '@/icons/kegosIcons';
import { recurringStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.RecurringInvoiceStatusEnum;
}>();

const { t } = useI18n();

const STATUS: Record<App.Enums.RecurringInvoiceStatusEnum, { tone: StatusTone; icon?: Component }> = {
    active: { tone: 'done' },
    paused: { tone: 'warning' },
    completed: { tone: 'neutral', icon: KG_ICONS.status.done },
    cancelled: { tone: 'neutral', icon: KG_ICONS.status.cancelled },
};

const entry = computed(() => STATUS[props.status]);
</script>

<template>
    <StatusChip :tone="entry.tone" :icon="entry.icon" :label="t(recurringStatusKey(props.status))" size="sm" />
</template>
