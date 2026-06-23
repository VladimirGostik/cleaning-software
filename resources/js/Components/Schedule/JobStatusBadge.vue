<script setup lang="ts">
    import { computed } from 'vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = withDefaults(
        defineProps<{
            status: App.Enums.JobStatusEnum;
            size?: 'sm' | 'md';
        }>(),
        { size: 'sm' },
    );

    const { t } = useTranslate();

    const badgeClass = computed<string>(() => {
        const map: Record<App.Enums.JobStatusEnum, string> = {
            planned: 'badge-info',
            unassigned: 'badge-warning',
            in_progress: 'badge-primary',
            completed: 'badge-success',
            unapproved: 'badge-error',
            cancelled: 'badge-ghost',
        };
        return map[props.status] ?? 'badge-ghost';
    });

    const sizeClass = computed<string>(() => (props.size === 'sm' ? 'badge-sm' : ''));
</script>

<template>
    <span :class="['badge', badgeClass, sizeClass]">
        {{ t('job_status.' + status) }}
    </span>
</template>
