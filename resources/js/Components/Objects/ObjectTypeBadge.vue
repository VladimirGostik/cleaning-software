<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { objectTypeKey } from '@/utils/enums';

const props = defineProps<{
    type: App.Enums.ObjectTypeEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip tone="neutral" :label="t(objectTypeKey(props.type))" size="sm" />
</template>
