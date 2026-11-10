<script setup lang="ts">
import FormField from './FormField.vue';

defineProps<{
    label: string;
    accept?: string;
    error?: string | null;
    disabled?: boolean;
    multiple?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: File | null];
    'update:files': [files: File[]];
}>();

function onChange(event: Event): void {
    const files = (event.target as HTMLInputElement).files;
    emit('update:modelValue', files && files.length > 0 ? files[0] : null);
    emit('update:files', files ? Array.from(files) : []);
}
</script>

<template>
    <FormField :label="label" :error="error">
        <input
            type="file"
            class="file-input w-full"
            :class="{ 'file-input-error': error }"
            :accept="accept"
            :multiple="multiple"
            :disabled="disabled"
            :aria-invalid="error ? 'true' : undefined"
            @change="onChange"
        />
    </FormField>
</template>
