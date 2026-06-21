<script setup lang="ts">
    import { computed } from 'vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = withDefaults(
        defineProps<{
            status: App.Enums.QuoteStatusEnum;
            size?: 'sm' | 'md';
        }>(),
        { size: 'sm' },
    );

    const { t } = useTranslate();

    const badgeClass = computed<string>(() => {
        const map: Record<App.Enums.QuoteStatusEnum, string> = {
            draft: 'badge-ghost',
            sent: 'badge-info',
            accepted: 'badge-success',
            rejected: 'badge-error',
            expired: 'badge-warning',
        };
        return map[props.status] ?? 'badge-ghost';
    });

    const sizeClass = computed<string>(() => (props.size === 'sm' ? 'badge-sm' : ''));
</script>

<template>
    <span :class="['badge gap-1', badgeClass, sizeClass]">
        {{ t('quote_status.' + status) }}
    </span>
</template>
