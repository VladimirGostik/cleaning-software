<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { documentTemplateScopeKey } from '@/utils/enums';

const props = defineProps<{
    scope: App.Enums.DocumentTemplateScopeEnum;
}>();

const { t } = useI18n();

const toneClass = computed(() => (props.scope === 'global' ? 'badge-info' : 'badge-ghost'));
</script>

<template>
    <span class="badge badge-sm" :class="toneClass">{{ t(documentTemplateScopeKey(scope)) }}</span>
</template>
