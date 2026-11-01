<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { KG_ICONS } from '@/icons/kegosIcons';

const props = defineProps<{
    isActive: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip
        :tone="props.isActive ? 'done' : 'neutral'"
        :icon="props.isActive ? undefined : KG_ICONS.status.cancelled"
        :label="props.isActive ? t('active') : t('inactive')"
        size="sm"
    />
</template>
