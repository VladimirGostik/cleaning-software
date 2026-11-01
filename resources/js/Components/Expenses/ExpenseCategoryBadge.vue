<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { expenseCategoryKey } from '@/utils/enums';

const props = defineProps<{
    category: App.Enums.ExpenseCategoryEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip tone="neutral" :label="t(expenseCategoryKey(props.category))" size="sm" />
</template>
