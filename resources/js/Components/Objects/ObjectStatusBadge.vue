<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';

const props = defineProps<{
    isActive: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip
        :tone="props.isActive ? 'done' : 'neutral'"
        :label="props.isActive ? t('active') : t('inactive')"
        size="sm"
    />
</template>
