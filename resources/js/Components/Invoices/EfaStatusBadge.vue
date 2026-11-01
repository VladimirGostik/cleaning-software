<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';

const props = defineProps<{
    status: App.Enums.EfaStatusEnum;
}>();

const { t } = useI18n();

const TONE: Record<App.Enums.EfaStatusEnum, StatusTone> = {
    none: 'neutral',
    ready: 'planned',
    generated: 'planned',
    submitted: 'in_progress',
    accepted: 'done',
    rejected: 'warning',
    failed: 'danger',
};

const label = computed(() => t(`efa_status_${props.status}`));
</script>

<template>
    <StatusChip :tone="TONE[props.status]" :label="label" size="sm" />
</template>
