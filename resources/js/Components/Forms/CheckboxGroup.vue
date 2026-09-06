<script setup lang="ts">
import { computed } from 'vue';
import { useFormContext } from './useFormContext';
import { useFieldError, callValidate } from './useFieldError';

export interface CheckboxOption {
    value: string;
    label: string;
    disabled?: boolean;
}

const props = defineProps<{
    field?: string;
    modelValue?: string[];
    options: CheckboxOption[];
    label?: string;
    error?: string | null;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
    change: [value: string[]];
}>();

const form = useFormContext();

const resolvedValue = computed<string[]>(() => {
    if (props.field && form) return ((form as Record<string, unknown>)[props.field] as string[]) ?? [];
    return props.modelValue ?? [];
});

const resolvedError = useFieldError(props, form);

function toggle(value: string): void {
    const next = resolvedValue.value.includes(value)
        ? resolvedValue.value.filter((v) => v !== value)
        : [...resolvedValue.value, value];
    if (props.field && form) {
        (form as Record<string, unknown>)[props.field] = next;
    }
    callValidate(form, props.field);
    emit('update:modelValue', next);
    emit('change', next);
}
</script>

<template>
    <div>
        <p v-if="label" class="text-sm font-medium mb-2">
            {{ label }}
        </p>
        <div class="flex flex-wrap gap-4">
            <label
                v-for="option in options"
                :key="option.value"
                class="flex items-center gap-2 cursor-pointer"
                :class="{ 'opacity-50 cursor-not-allowed': disabled || option.disabled }"
            >
                <input
                    type="checkbox"
                    class="checkbox checkbox-sm checkbox-primary"
                    :checked="resolvedValue.includes(option.value)"
                    :disabled="disabled || option.disabled"
                    @change="toggle(option.value)"
                />
                <span class="text-sm capitalize">{{ option.label }}</span>
            </label>
        </div>
        <p v-if="resolvedError" class="text-error text-sm mt-1">
            {{ resolvedError }}
        </p>
    </div>
</template>
