<script setup lang="ts">
    import { useTranslate } from '@/Composables/useTranslate';

    defineProps<{
        active: boolean;
    }>();

    const { t } = useTranslate();
</script>

<template>
    <span :class="['badge', active ? 'badge-success' : 'badge-ghost']">
        {{ active ? t('employees.status.active') : t('employees.status.inactive') }}
    </span>
</template>
