<script setup lang="ts">
import { useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';

import QuietTextarea from '@/Components/Forms/QuietTextarea.vue';
import { useFieldDraft } from '@/Composables/useFieldDraft';

const props = defineProps<{
    description: string | null;
    editable: boolean;
    disabled: boolean;
    error?: string | null;
}>();

const emit = defineEmits<{
    save: [description: string | null, debounced: boolean];
}>();

const { t } = useI18n();
const root = useTemplateRef<HTMLElement>('root');
const { draft, focused, commit } = useFieldDraft(() => props.description ?? '');

function normalize(value: string): string | null {
    return value === '' ? null : value;
}

function onInput(value: string): void {
    draft.value = value;
    emit('save', normalize(value), true);
}

defineExpose({
    focus: () => root.value?.querySelector('textarea')?.focus(),
});

function onBlur(): void {
    focused.value = false;

    const value = commit();
    if (value !== null) emit('save', normalize(value), false);
}
</script>

<template>
    <div v-if="editable" ref="root" @focusin="focused = true" @focusout="onBlur">
        <QuietTextarea
            :model-value="draft"
            :label="t('description')"
            :placeholder="t('task_add_description')"
            :error="error"
            :disabled="disabled"
            :maxlength="5000"
            :rows="3"
            @update:model-value="onInput"
        />
    </div>
    <p v-else-if="description" class="kg-quiet-textarea kg-quiet--static whitespace-pre-wrap">{{ description }}</p>
</template>
