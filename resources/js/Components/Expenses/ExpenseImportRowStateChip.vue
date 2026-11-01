<script setup lang="ts">
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import AddIcon from '~icons/material-symbols/add-circle-outline-rounded';
import DeleteIcon from '~icons/material-symbols/delete-outline-rounded';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';

const props = defineProps<{
    state: App.Enums.ExpenseImportRowStateEnum;
}>();

const { t } = useI18n();

const APPEARANCE: Record<App.Enums.ExpenseImportRowStateEnum, { tone: StatusTone; icon?: Component }> = {
    new: { tone: 'planned', icon: AddIcon },
    already_imported: { tone: 'done' },
    possible_match: { tone: 'warning' },
    previously_deleted: { tone: 'neutral', icon: DeleteIcon },
};

const appearance = computed(() => APPEARANCE[props.state]);
</script>

<template>
    <StatusChip :tone="appearance.tone" :icon="appearance.icon" :label="t(`expense_import_state_${state}`)" size="sm" />
</template>
