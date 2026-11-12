<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';
import { contractStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.ContractStatusEnum;
}>();

const { t } = useI18n();

const STATUS: Record<App.Enums.ContractStatusEnum, { tone: StatusTone }> = {
    draft: { tone: 'neutral' },
    active: { tone: 'done' },
    expired: { tone: 'warning' },
    terminated: { tone: 'danger' },
};

const entry = computed(() => STATUS[props.status]);
</script>

<template>
    <StatusChip :tone="entry.tone" :label="t(contractStatusKey(props.status))" size="sm" />
</template>
