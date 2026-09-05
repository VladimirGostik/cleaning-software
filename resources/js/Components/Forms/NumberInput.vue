<script setup lang="ts">
import FormField from './FormField.vue';

defineProps<{
    modelValue: number | null;
    label: string;
    required?: boolean;
    error?: string | null;
    min?: number;
    max?: number;
    step?: number | 'any';
    disabled?: boolean;
    placeholder?: string;
}>();

defineEmits<{
    'update:modelValue': [value: number | null];
}>();

function parseValue(event: Event): number | null {
    const raw = (event.target as HTMLInputElement).value;
    return raw === '' ? null : Number(raw);
}
</script>

<template>
    <FormField
        :label="label"
        :error="error"
        :required="required"
    >
        <input
            :value="modelValue ?? ''"
            type="number"
            class="input w-full"
            :class="{ 'input-error': error }"
            :required="required"
            :min="min"
            :max="max"
            :step="step"
            :disabled="disabled"
            :placeholder="placeholder"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="error ? 'true' : undefined"
            @input="$emit('update:modelValue', parseValue($event))"
        />
    </FormField>
</template>
