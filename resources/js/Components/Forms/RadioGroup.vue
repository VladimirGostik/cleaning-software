<script setup lang="ts">
import { computed } from 'vue';
import { useFormContext, callValidate } from './useFormContext';
import { useFieldError } from './useFieldError';

export interface RadioOption {
    value: string;
    label: string;
    disabled?: boolean;
}

const props = withDefaults(
    defineProps<{
        field?: string;
        modelValue?: string;
        options: RadioOption[];
        label?: string;
        required?: boolean;
        error?: string;
    }>(),
    {
        field: undefined,
        modelValue: undefined,
        label: undefined,
        required: false,
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

function onChange(value: string): void {
    if (props.field && form) {
        (form as unknown as Record<string, unknown>)[props.field] = value;
        callValidate(form, props.field);
    }
    emit('update:modelValue', value);
}
</script>

<template>
    <fieldset class="fieldset w-full">
        <legend v-if="label" class="fieldset-legend">
            {{ label }}
            <span v-if="required" class="text-error" aria-hidden="true">*</span>
        </legend>
        <div class="flex gap-4 flex-wrap" role="group" :aria-label="label">
            <label
                v-for="opt in options"
                :key="opt.value"
                class="flex items-center gap-2 cursor-pointer"
                :class="{ 'opacity-50 cursor-not-allowed': opt.disabled }"
            >
                <input
                    type="radio"
                    :value="opt.value"
                    :checked="resolvedValue === opt.value"
                    :disabled="opt.disabled"
                    :aria-required="required ? 'true' : undefined"
                    class="radio"
                    @change="onChange(opt.value)"
                />
                {{ opt.label }}
            </label>
        </div>
        <span v-if="resolvedError" class="fieldset-label text-error" role="alert">{{ resolvedError }}</span>
    </fieldset>
</template>
