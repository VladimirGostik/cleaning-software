<script setup lang="ts">
import { computed } from 'vue';
import { useFormContext, callValidate } from './useFormContext';
import { useFieldError } from './useFieldError';

const props = withDefaults(
    defineProps<{
        field?: string;
        modelValue?: boolean;
        label?: string;
        required?: boolean;
        disabled?: boolean;
        error?: string;
    }>(),
    {
        field: undefined,
        modelValue: undefined,
        label: undefined,
        required: false,
        disabled: false,
        error: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const form = useFormContext();
const resolvedError = useFieldError(props, form);

const resolvedValue = computed<boolean>(() => {
    if (props.field && form) {
        return (form as unknown as Record<string, unknown>)[props.field] as boolean ?? false;
    }
    return props.modelValue ?? false;
});

function onChange(event: Event): void {
    const value = (event.target as HTMLInputElement).checked;
    if (props.field && form) {
        (form as unknown as Record<string, unknown>)[props.field] = value;
        callValidate(form, props.field);
    }
    emit('update:modelValue', value);
}
</script>

<template>
    <div class="fieldset w-full">
        <label class="flex items-center gap-3 cursor-pointer">
            <input
                type="checkbox"
                :checked="resolvedValue"
                :required="required"
                :disabled="disabled"
                :aria-required="required ? 'true' : undefined"
                :aria-invalid="resolvedError ? 'true' : undefined"
                class="toggle toggle-primary"
                @change="onChange"
            />
            <span v-if="label" class="text-sm">
                {{ label }}
                <span v-if="required" class="text-error" aria-hidden="true">*</span>
            </span>
        </label>
        <span v-if="resolvedError" class="fieldset-label text-error" role="alert">{{ resolvedError }}</span>
    </div>
</template>
