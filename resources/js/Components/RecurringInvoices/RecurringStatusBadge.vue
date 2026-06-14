<script setup lang="ts">
    import { computed } from 'vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = withDefaults(
        defineProps<{
            status: App.Enums.RecurringInvoiceStatusEnum;
            size?: 'sm' | 'md';
        }>(),
        {
            size: 'sm',
        },
    );

    const { t } = useTranslate();

    const badgeClass = computed<string>(() => {
        const map: Record<App.Enums.RecurringInvoiceStatusEnum, string> = {
            active: 'badge-success',
            paused: 'badge-warning',
            completed: 'badge-ghost',
            cancelled: 'badge-error',
        };
        return map[props.status] ?? 'badge-ghost';
    });

    const sizeClass = computed<string>(() => (props.size === 'sm' ? 'badge-sm' : ''));
</script>

<template>
    <span :class="['badge gap-1', badgeClass, sizeClass]">
        {{ t('recurring_invoices.status.' + status) }}
    </span>
</template>
