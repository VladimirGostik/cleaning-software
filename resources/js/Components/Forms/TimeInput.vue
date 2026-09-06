<script setup lang="ts">
import FormField from './FormField.vue';

withDefaults(
    defineProps<{
        modelValue: string | null;
        label: string;
        required?: boolean;
        error?: string | null;
        min?: string;
        max?: string;
        step?: number;
        disabled?: boolean;
    }>(),
    { step: 300 },
);

defineEmits<{
    'update:modelValue': [value: string | null];
}>();
</script>

<template>
    <FormField :label="label" :error="error" :required="required">
        <input
            :value="modelValue ?? ''"
            type="time"
            class="input w-full"
            :class="{ 'input-error': error }"
            :required="required"
            :min="min"
            :max="max"
            :step="step"
            :disabled="disabled"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="error ? 'true' : undefined"
            @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value || null)"
        />
    </FormField>
</template>
