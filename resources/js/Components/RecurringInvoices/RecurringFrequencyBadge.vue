<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { recurringFrequencyKey } from '@/utils/enums';

const props = defineProps<{
    frequency: App.Enums.RecurringFrequencyEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <span class="badge badge-sm badge-ghost">{{ t(recurringFrequencyKey(props.frequency)) }}</span>
</template>
