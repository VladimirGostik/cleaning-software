<script setup lang="ts">
    import { computed } from 'vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = withDefaults(
        defineProps<{
            status: App.Enums.InvoiceStatusEnum;
            size?: 'sm' | 'md';
        }>(),
        {
            size: 'sm',
        },
    );

    const { t } = useTranslate();

    const badgeClass = computed<string>(() => {
        const map: Record<App.Enums.InvoiceStatusEnum, string> = {
            draft: 'badge-ghost',
            issued: 'badge-primary',
            paid: 'badge-success',
            overdue: 'badge-error',
            cancelled: 'badge-warning',
        };
        return map[props.status] ?? 'badge-ghost';
    });

    const sizeClass = computed<string>(() => (props.size === 'sm' ? 'badge-sm' : ''));
</script>

<template>
    <span :class="['badge gap-1', badgeClass, sizeClass]">
        {{ t('invoice_status.' + status) }}
    </span>
</template>
