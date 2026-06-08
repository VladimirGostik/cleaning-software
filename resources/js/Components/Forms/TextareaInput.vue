<script setup lang="ts">
import { computed } from 'vue';
import FormField from './FormField.vue';
import { useFormContext, callValidate } from './useFormContext';
import { useFieldError } from './useFieldError';

const props = withDefaults(
    defineProps<{
        field?: string;
        modelValue?: string;
        label?: string;
        placeholder?: string;
        rows?: number;
        required?: boolean;
        disabled?: boolean;
        error?: string;
    }>(),
    {
        field: undefined,
        modelValue: undefined,
        label: undefined,
        placeholder: undefined,
        rows: 4,
        required: false,
        disabled: false,
        error: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const form = useFormContext();
const resolvedError = useFieldError(props, form);

const resolvedValue = computed<string>(() => {
    if (props.field && form) {
        return (form as unknown as Record<string, unknown>)[props.field] as string ?? '';
    }
    return props.modelValue ?? '';
});

function onInput(event: Event): void {
    const value = (event.target as HTMLTextAreaElement).value;
    if (props.field && form) {
        (form as unknown as Record<string, unknown>)[props.field] = value;
        callValidate(form, props.field);
    }
    emit('update:modelValue', value);
}
</script>

<template>
    <FormField :label="label" :error="resolvedError" :required="required">
        <textarea
            :value="resolvedValue"
            :rows="rows"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="resolvedError ? 'true' : undefined"
            class="textarea w-full"
            :class="{ 'textarea-error': resolvedError }"
            @input="onInput"
        />
    </FormField>
</template>
