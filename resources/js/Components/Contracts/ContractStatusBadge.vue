<script setup lang="ts">
    import { computed } from 'vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = withDefaults(
        defineProps<{
            status: App.Enums.ContractStatusEnum;
            size?: 'sm' | 'md';
        }>(),
        {
            size: 'sm',
        },
    );

    const { t } = useTranslate();

    const badgeClass = computed<string>(() => {
        const map: Record<App.Enums.ContractStatusEnum, string> = {
            draft: 'badge-ghost',
            active: 'badge-success',
            expired: 'badge-warning',
            terminated: 'badge-error',
        };
        return map[props.status] ?? 'badge-ghost';
    });

    const sizeClass = computed<string>(() => (props.size === 'sm' ? 'badge-sm' : ''));
</script>

<template>
    <span :class="['badge gap-1', badgeClass, sizeClass]">
        {{ t('contract_status.' + status) }}
    </span>
</template>
