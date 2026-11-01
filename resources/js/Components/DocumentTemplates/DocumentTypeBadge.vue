<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { documentTypeKey } from '@/utils/enums';

const props = defineProps<{
    type: App.Enums.DocumentTypeEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip tone="neutral" :label="t(documentTypeKey(props.type))" size="sm" />
</template>
