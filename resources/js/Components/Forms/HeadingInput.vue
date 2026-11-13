<script setup lang="ts">
import { useId, useTemplateRef } from 'vue';
import { useAutosize } from '@/Composables/useAutosize';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    modelValue: string;
    label: string;
    error?: string | null;
    disabled?: boolean;
    maxlength?: number;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const id = useId();
const textarea = useTemplateRef<HTMLTextAreaElement>('textarea');

useAutosize(textarea, () => props.modelValue);
</script>

<template>
    <div>
        <label :for="id" class="sr-only">{{ label }}</label>
        <textarea
            :id="id"
            ref="textarea"
            v-bind="$attrs"
            :value="modelValue"
            rows="1"
            class="kg-heading-input"
            :class="{ 'kg-heading-input-error': error }"
            :disabled="disabled"
            :maxlength="maxlength"
            :aria-invalid="error ? 'true' : undefined"
            @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />
        <p v-if="error" class="mt-1 text-sm text-error">{{ error }}</p>
    </div>
</template>
