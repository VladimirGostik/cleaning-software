<script setup lang="ts">
import FormField from './FormField.vue';

defineProps<{
    modelValue: string;
    label: string;
    required?: boolean;
    error?: string | null;
    disabled?: boolean;
}>();

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <FormField :label="label" :error="error" :required="required">
        <input
            :value="modelValue"
            type="datetime-local"
            class="input w-full"
            :class="{ 'input-error': error }"
            :required="required"
            :disabled="disabled"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="error ? 'true' : undefined"
            @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
    </FormField>
</template>
