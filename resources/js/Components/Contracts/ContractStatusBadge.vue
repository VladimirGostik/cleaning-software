<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { contractStatusKey } from '@/utils/enums';

const props = defineProps<{
    status: App.Enums.ContractStatusEnum;
}>();

const { t } = useI18n();

const BADGE_CLASS: Record<App.Enums.ContractStatusEnum, string> = {
    draft: 'badge-ghost',
    active: 'badge-success',
    expired: 'badge-warning',
    terminated: 'badge-error',
};

const badgeClass = computed(() => BADGE_CLASS[props.status]);
</script>

<template>
    <span class="badge badge-sm" :class="badgeClass">{{ t(contractStatusKey(status)) }}</span>
</template>
