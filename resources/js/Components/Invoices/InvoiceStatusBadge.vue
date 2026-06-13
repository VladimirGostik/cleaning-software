<script setup lang="ts">
    import { computed } from 'vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = defineProps<{
        status: App.Enums.InvoiceStatusEnum;
    }>();

    const { t } = useTranslate();

    const badgeClass = computed<string>(() => {
        const map: Record<App.Enums.InvoiceStatusEnum, string> = {
            draft: 'badge-ghost',
            issued: 'badge-info',
            paid: 'badge-success',
            overdue: 'badge-error',
            cancelled: 'badge-warning',
        };
        return map[props.status] ?? 'badge-ghost';
    });
</script>

<template>
    <span :class="['badge gap-1', badgeClass]">
        {{ t('invoice_status.' + status) }}
    </span>
</template>
