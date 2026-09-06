<script setup lang="ts">
import { nextTick, ref } from 'vue';
import FormField from './FormField.vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        label: string;
        required?: boolean;
        error?: string | null;
        rows?: number;
        disabled?: boolean;
        placeholder?: string;
    }>(),
    { rows: 4 },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

// Imperative DOM access only — needed to read/restore caret position for insertAtCursor.
const textareaRef = ref<HTMLTextAreaElement | null>(null);

function insertAtCursor(text: string): void {
    const el = textareaRef.value;
    if (!el) return;

    const start = el.selectionStart ?? props.modelValue.length;
    const end = el.selectionEnd ?? start;

    emit('update:modelValue', props.modelValue.slice(0, start) + text + props.modelValue.slice(end));

    void nextTick(() => {
        el.focus();
        const pos = start + text.length;
        el.setSelectionRange(pos, pos);
    });
}

defineExpose({ insertAtCursor });
</script>

<template>
    <FormField :label="label" :error="error" :required="required">
        <textarea
            ref="textareaRef"
            :value="modelValue"
            class="textarea w-full"
            :class="{ 'textarea-error': error }"
            :required="required"
            :rows="rows"
            :disabled="disabled"
            :placeholder="placeholder"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="error ? 'true' : undefined"
            @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />
    </FormField>
</template>
