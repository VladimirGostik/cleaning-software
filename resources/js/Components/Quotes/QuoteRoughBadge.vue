<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';

const { t } = useI18n();
</script>

<template>
    <span :title="t('quote_rough_hint')">
        <StatusChip tone="warning" :label="t('quote_rough')" size="sm" />
    </span>
</template>
