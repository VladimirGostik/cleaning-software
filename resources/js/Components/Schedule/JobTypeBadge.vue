<script setup lang="ts">
    import { computed } from 'vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = withDefaults(
        defineProps<{
            type: App.Enums.JobTypeEnum;
            size?: 'sm' | 'md';
        }>(),
        { size: 'sm' },
    );

    const { t } = useTranslate();

    const badgeClass = computed<string>(() => {
        const map: Record<App.Enums.JobTypeEnum, string> = {
            regular: 'badge-secondary',
            one_off: 'badge-accent',
            special: 'badge-primary',
        };
        return map[props.type] ?? 'badge-ghost';
    });

    const sizeClass = computed<string>(() => (props.size === 'sm' ? 'badge-sm' : ''));
</script>

<template>
    <span :class="['badge', badgeClass, sizeClass]">
        {{ t('job_type.' + type) }}
    </span>
</template>
