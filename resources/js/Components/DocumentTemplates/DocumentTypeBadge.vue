<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { documentTypeKey } from '@/utils/enums';

defineProps<{
    type: App.Enums.DocumentTypeEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <span class="badge badge-sm badge-outline">{{ t(documentTypeKey(type)) }}</span>
</template>
