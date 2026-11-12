<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { clientTypeKey } from '@/utils/enums';

const props = defineProps<{
    type: App.Enums.ClientTypeEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip tone="neutral" :label="t(clientTypeKey(props.type))" size="sm" />
</template>
