<script setup lang="ts">
import FormField from './FormField.vue';

withDefaults(
    defineProps<{
        modelValue: string;
        label: string;
        required?: boolean;
        error?: string | null;
        rows?: number;
        disabled?: boolean;
        placeholder?: string;
    }>(),
    { rows: 4 },
);

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <FormField
        :label="label"
        :error="error"
        :required="required"
    >
        <textarea
            :value="modelValue"
            class="textarea w-full"
            :class="{ 'textarea-error': error }"
            :required="required"
            :rows="rows"
            :disabled="disabled"
            :placeholder="placeholder"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="error ? 'true' : undefined"
            @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />
    </FormField>
</template>
