<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { SaveState } from '@/Composables/useTaskInlineEdit';

const props = defineProps<{
    state: SaveState;
}>();

const { t } = useI18n();

const KEYS: Record<Exclude<SaveState, 'idle'>, string> = {
    saving: 'task_saving',
    saved: 'task_saved',
    error: 'task_save_failed',
};

const text = computed(() => (props.state === 'idle' ? '' : t(KEYS[props.state])));
</script>

<template>
    <p role="status" aria-live="polite" class="text-sm" :class="state === 'error' ? 'text-error' : 'text-muted'">
        {{ text }}
    </p>
</template>
