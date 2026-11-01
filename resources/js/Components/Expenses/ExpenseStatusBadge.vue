<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { expenseStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.ExpenseStatusEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip
        :tone="props.status === 'confirmed' ? 'done' : 'warning'"
        :label="t(expenseStatusKey(props.status))"
        size="sm"
    />
</template>
