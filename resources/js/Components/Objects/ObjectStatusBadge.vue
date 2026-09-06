<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
    isActive: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <span class="badge badge-sm" :class="isActive ? 'badge-success' : 'badge-ghost'">
        {{ isActive ? t('active') : t('inactive') }}
    </span>
</template>
