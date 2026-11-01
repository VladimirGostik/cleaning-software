<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { documentTemplateStateKey } from '@/utils/enums';

const props = defineProps<{
    state: App.Enums.DocumentTemplateStateEnum;
}>();

const { t } = useI18n();

const toneClass = computed(() => (props.state === 'base_changed' ? 'badge-warning' : 'badge-outline'));
</script>

<template>
    <span class="badge badge-sm" :class="toneClass">{{ t(documentTemplateStateKey(state)) }}</span>
</template>
