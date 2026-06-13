<script setup lang="ts">
import { computed } from 'vue';
import FormField from './FormField.vue';
import { useFormContext, callValidate } from './useFormContext';
import { useFieldError } from './useFieldError';

export interface SelectOption {
    value: string | number;
    label: string;
}

const props = withDefaults(
    defineProps<{
        field?: string;
        modelValue?: string | number;
        options: SelectOption[];
        label?: string;
        placeholder?: string;
        required?: boolean;
        disabled?: boolean;
        error?: string;
    }>(),
    {
        field: undefined,
        modelValue: undefined,
        label: undefined,
        placeholder: undefined,
        required: false,
        disabled: false,
        error: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | number];
}>();

const form = useFormContext();
const resolvedError = useFieldError(props, form);

const resolvedValue = computed<string | number>(() => {
    if (props.field && form) {
        return (form as unknown as Record<string, unknown>)[props.field] as string | number ?? '';
    }
    return props.modelValue ?? '';
});

function onChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    if (props.field && form) {
        (form as unknown as Record<string, unknown>)[props.field] = value;
        callValidate(form, props.field);
    }
    emit('update:modelValue', value);
}
</script>

<template>
    <FormField :label="label" :error="resolvedError" :required="required">
        <select
            :value="resolvedValue"
            :required="required"
            :disabled="disabled"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="resolvedError ? 'true' : undefined"
            class="select select-bordered w-full"
            :class="{ 'select-error': resolvedError }"
            @change="onChange"
        >
            <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
            <option v-for="opt in options" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>
    </FormField>
</template>
