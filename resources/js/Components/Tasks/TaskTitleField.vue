<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import HeadingInput from '@/Components/Forms/HeadingInput.vue';
import { useFieldDraft } from '@/Composables/useFieldDraft';

const props = defineProps<{
    title: string;
    editable: boolean;
    disabled: boolean;
    error?: string | null;
}>();

const emit = defineEmits<{
    save: [title: string];
}>();

const { t } = useI18n();
const { draft, focused, commit, revert, isDirty } = useFieldDraft(() => props.title);

function onInput(value: string): void {
    draft.value = value.replace(/\s*\n+\s*/g, ' ');
}

function save(): void {
    focused.value = false;
    draft.value = draft.value.trim();

    const value = commit();
    if (value !== null) emit('save', value);
}

function onEscape(event: KeyboardEvent): void {
    if (!isDirty()) return;

    event.stopPropagation();
    revert();
    (event.target as HTMLElement).blur();
}

function onEnter(event: KeyboardEvent): void {
    event.preventDefault();
    (event.target as HTMLElement).blur();
}
</script>

<template>
    <HeadingInput
        v-if="editable"
        :model-value="draft"
        :label="t('task_title')"
        :error="error"
        :disabled="disabled"
        :maxlength="200"
        @update:model-value="onInput"
        @focus="focused = true"
        @blur="save"
        @keydown.enter="onEnter"
        @keydown.esc="onEscape"
    />
    <h2 v-else class="kg-heading-input kg-quiet--static break-words">{{ title }}</h2>
</template>
