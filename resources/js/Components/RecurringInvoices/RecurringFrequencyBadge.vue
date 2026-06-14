<script setup lang="ts">
    import { useTranslate } from '@/Composables/useTranslate';

    defineProps<{
        frequency: App.Enums.RecurringFrequencyEnum;
    }>();

    const { t } = useTranslate();
</script>

<template>
    <span class="badge badge-ghost badge-sm">
        {{ t('recurring_invoices.frequency.' + frequency) }}
    </span>
</template>
