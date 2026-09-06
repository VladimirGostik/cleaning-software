<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { quoteStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.QuoteStatusEnum;
}>();

const { t } = useI18n();

const BADGE_CLASS: Record<App.Enums.QuoteStatusEnum, string> = {
    draft: 'badge-ghost',
    sent: 'badge-info',
    accepted: 'badge-success',
    rejected: 'badge-error',
    expired: 'badge-warning',
};

const badgeClass = computed(() => BADGE_CLASS[props.status]);
</script>

<template>
    <span class="badge badge-sm" :class="badgeClass">{{ t(quoteStatusKey(status)) }}</span>
</template>
