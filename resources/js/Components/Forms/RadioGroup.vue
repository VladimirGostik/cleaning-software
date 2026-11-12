<script setup lang="ts">
import { computed } from 'vue';
import FormField from './FormField.vue';
import { useFormContext } from './useFormContext';
import { useFieldError, callValidate } from './useFieldError';

export interface RadioOption {
    value: string;
    label: string;
    disabled?: boolean;
}

const props = defineProps<{
    field?: string;
    modelValue?: string | null;
    options: RadioOption[];
    label: string;
    required?: boolean;
    error?: string | null;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const form = useFormContext();

const resolvedValue = computed<string | null>(() => {
    if (props.field && form) return String((form as Record<string, unknown>)[props.field] ?? '') || null;
    return props.modelValue ?? null;
});

const resolvedError = useFieldError(props, form);

function onSelect(value: string): void {
    if (props.field && form) {
        (form as Record<string, unknown>)[props.field] = value;
    }
    callValidate(form, props.field);
    emit('update:modelValue', value);
}
</script>

<template>
    <FormField :label="label" :error="resolvedError" :required="required">
        <div class="mt-1 flex flex-wrap gap-x-4">
            <label
                v-for="option in options"
                :key="option.value"
                class="flex min-h-11 cursor-pointer items-center gap-2 lg:min-h-0"
                :class="{ 'opacity-50 cursor-not-allowed': disabled || option.disabled }"
            >
                <input
                    type="radio"
                    class="radio radio-sm radio-primary"
                    :value="option.value"
                    :checked="resolvedValue === option.value"
                    :disabled="disabled || option.disabled"
                    :required="required"
                    @change="onSelect(option.value)"
                />
                <span class="text-sm">{{ option.label }}</span>
            </label>
        </div>
    </FormField>
</template>
