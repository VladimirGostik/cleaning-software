<script setup lang="ts">
import { useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';

import HeadingInput from '@/Components/Forms/HeadingInput.vue';
import { useFieldDraft } from '@/Composables/useFieldDraft';

const props = defineProps<{
    title: string;
    editable: boolean;
    disabled: boolean;
    error?: string | null;
    live?: boolean;
    describedby?: string;
}>();

const emit = defineEmits<{
    save: [title: string];
    input: [title: string];
    enter: [];
}>();

const field = useTemplateRef<{ $el: HTMLElement }>('field');

const { t } = useI18n();
const { draft, focused, commit, revert, isDirty } = useFieldDraft(() => props.title);

function onInput(value: string): void {
    draft.value = value.replace(/\s*\n+\s*/g, ' ');
    if (props.live) emit('input', draft.value);
}

function save(): void {
    focused.value = false;
    draft.value = draft.value.trim();

    const value = commit();
    if (value !== null) emit('save', value);
}

function onEscape(event: KeyboardEvent): void {
    if (props.live || !isDirty()) return;

    event.stopPropagation();
    revert();
    (event.target as HTMLElement).blur();
}

function onEnter(event: KeyboardEvent): void {
    event.preventDefault();

    if (props.live) {
        emit('enter');
        return;
    }

    (event.target as HTMLElement).blur();
}

defineExpose({
    focus: () => field.value?.$el.querySelector('textarea')?.focus(),
});
</script>

<template>
    <HeadingInput
        v-if="editable"
        ref="field"
        :autofocus="live"
        :placeholder="live ? t('task_title') : undefined"
        :aria-describedby="describedby"
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
