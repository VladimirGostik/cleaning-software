<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';

const props = defineProps<{
    state: App.Enums.ExpenseImportRowStateEnum;
}>();

const { t } = useI18n();

const APPEARANCE: Record<App.Enums.ExpenseImportRowStateEnum, { tone: StatusTone }> = {
    new: { tone: 'planned' },
    already_imported: { tone: 'done' },
    possible_match: { tone: 'warning' },
    previously_deleted: { tone: 'neutral' },
};

const appearance = computed(() => APPEARANCE[props.state]);
</script>

<template>
    <StatusChip :tone="appearance.tone" :label="t(`expense_import_state_${state}`)" size="sm" />
</template>
