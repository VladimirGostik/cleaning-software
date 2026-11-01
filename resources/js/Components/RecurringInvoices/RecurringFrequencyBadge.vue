<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { recurringFrequencyKey } from '@/utils/enums';

const props = defineProps<{
    frequency: App.Enums.RecurringFrequencyEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip tone="neutral" :label="t(recurringFrequencyKey(props.frequency))" size="sm" />
</template>
