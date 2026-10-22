<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    status: App.Enums.EfaStatusEnum;
}>();

const { t } = useI18n();

const BADGE_CLASS: Record<App.Enums.EfaStatusEnum, string> = {
    none: 'badge-ghost',
    ready: 'badge-info',
    generated: 'badge-primary',
    submitted: 'badge-primary',
    accepted: 'badge-success',
    rejected: 'badge-warning',
    failed: 'badge-error',
};

const label = computed(() => t(`efa_status_${props.status}`));
</script>

<template>
    <span class="badge badge-sm" :class="BADGE_CLASS[status]">{{ label }}</span>
</template>
