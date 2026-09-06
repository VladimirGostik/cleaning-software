<script setup lang="ts">
import FormField from './FormField.vue';

defineProps<{
    modelValue: string | null;
    label: string;
    required?: boolean;
    error?: string | null;
    min?: string;
    max?: string;
    disabled?: boolean;
}>();

defineEmits<{
    'update:modelValue': [value: string | null];
}>();
</script>

<template>
    <FormField :label="label" :error="error" :required="required">
        <input
            :value="modelValue ?? ''"
            type="date"
            class="input w-full"
            :class="{ 'input-error': error }"
            :required="required"
            :min="min"
            :max="max"
            :disabled="disabled"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="error ? 'true' : undefined"
            @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value || null)"
        />
    </FormField>
</template>
