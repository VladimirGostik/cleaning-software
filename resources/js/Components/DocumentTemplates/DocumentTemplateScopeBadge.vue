<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { documentTemplateScopeKey } from '@/utils/enums';

const props = defineProps<{
    scope: App.Enums.DocumentTemplateScopeEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip tone="neutral" :label="t(documentTemplateScopeKey(props.scope))" size="sm" />
</template>
