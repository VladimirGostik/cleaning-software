<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';
import { recurringStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.RecurringInvoiceStatusEnum;
}>();

const { t } = useI18n();

const STATUS: Record<App.Enums.RecurringInvoiceStatusEnum, { tone: StatusTone }> = {
    active: { tone: 'done' },
    paused: { tone: 'warning' },
    completed: { tone: 'neutral' },
    cancelled: { tone: 'neutral' },
};

const entry = computed(() => STATUS[props.status]);
</script>

<template>
    <StatusChip :tone="entry.tone" :label="t(recurringStatusKey(props.status))" size="sm" />
</template>
