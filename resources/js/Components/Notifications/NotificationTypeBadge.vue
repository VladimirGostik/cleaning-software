<script setup lang="ts">
    import { computed } from 'vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = defineProps<{
        type: string;
    }>();

    const { t } = useTranslate();

    const badgeClass = computed<string>(() => {
        if (props.type.includes('overdue') || props.type.includes('expired')) return 'badge-error';
        if (props.type.includes('expiring')) return 'badge-warning';
        if (props.type.includes('sent') || props.type.includes('issued')) return 'badge-info';
        return 'badge-ghost';
    });
</script>

<template>
    <span :class="['badge badge-sm', badgeClass]">
        {{ t('notification_type.' + type) }}
    </span>
</template>
