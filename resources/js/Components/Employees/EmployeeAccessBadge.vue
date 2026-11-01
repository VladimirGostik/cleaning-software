<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';

const props = defineProps<{
    hasAccess: boolean;
    invitationPending: boolean;
}>();

const { t } = useI18n();

const state = computed<{ tone: StatusTone; label: string }>(() => {
    if (!props.hasAccess) return { tone: 'neutral', label: t('employee_access_none') };
    if (props.invitationPending) return { tone: 'warning', label: t('employee_access_pending') };
    return { tone: 'done', label: t('employee_access_active') };
});
</script>

<template>
    <StatusChip :tone="state.tone" :label="state.label" size="sm" />
</template>
