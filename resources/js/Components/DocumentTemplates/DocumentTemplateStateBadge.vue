<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { documentTemplateStateKey } from '@/utils/enums';

const props = defineProps<{
    state: App.Enums.DocumentTemplateStateEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <StatusChip
        :tone="props.state === 'base_changed' ? 'warning' : 'neutral'"
        :label="t(documentTemplateStateKey(props.state))"
        size="sm"
    />
</template>
