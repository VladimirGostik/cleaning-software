<script setup lang="ts">
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';
import { KG_ICONS } from '@/icons/kegosIcons';
import { quoteStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.QuoteStatusEnum;
}>();

const { t } = useI18n();

const STATUS: Record<App.Enums.QuoteStatusEnum, { tone: StatusTone; icon?: Component }> = {
    draft: { tone: 'neutral', icon: KG_ICONS.status.draft },
    sent: { tone: 'planned' },
    accepted: { tone: 'done' },
    rejected: { tone: 'danger' },
    expired: { tone: 'warning' },
};

const entry = computed(() => STATUS[props.status]);
</script>

<template>
    <StatusChip :tone="entry.tone" :icon="entry.icon" :label="t(quoteStatusKey(props.status))" size="sm" />
</template>
