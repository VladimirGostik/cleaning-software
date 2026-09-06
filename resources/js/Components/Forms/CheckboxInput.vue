<script setup lang="ts">
import { computed } from 'vue';
import { useFormContext } from './useFormContext';
import { useFieldError, callValidate } from './useFieldError';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    field?: string;
    modelValue?: boolean;
    label: string;
    error?: string | null;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const form = useFormContext();

const resolvedValue = computed(() => {
    if (props.field && form) return Boolean((form as Record<string, unknown>)[props.field]);
    return props.modelValue ?? false;
});

const resolvedError = useFieldError(props, form);

function onNativeChange(event: Event) {
    const checked = (event.target as HTMLInputElement).checked;
    if (props.field && form) {
        (form as Record<string, unknown>)[props.field] = checked;
    }
    callValidate(form, props.field);
    emit('update:modelValue', checked);
}
</script>

<template>
    <label class="flex items-center gap-2 cursor-pointer">
        <input
            v-bind="$attrs"
            :checked="resolvedValue"
            type="checkbox"
            class="checkbox checkbox-sm checkbox-primary"
            :disabled="disabled"
            :aria-invalid="resolvedError ? 'true' : undefined"
            @change="onNativeChange"
        />
        <span class="text-sm">{{ label }}</span>
        <p v-if="resolvedError" class="text-error text-sm">
            {{ resolvedError }}
        </p>
    </label>
</template>
