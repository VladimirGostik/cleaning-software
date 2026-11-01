<script setup lang="ts">
import FormField from './FormField.vue';

defineProps<{
    label: string;
    accept?: string;
    error?: string | null;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: File | null];
}>();

function onChange(event: Event): void {
    const files = (event.target as HTMLInputElement).files;
    emit('update:modelValue', files && files.length > 0 ? files[0] : null);
}
</script>

<template>
    <FormField :label="label" :error="error">
        <input
            type="file"
            class="file-input w-full"
            :class="{ 'file-input-error': error }"
            :accept="accept"
            :disabled="disabled"
            :aria-invalid="error ? 'true' : undefined"
            @change="onChange"
        />
    </FormField>
</template>
