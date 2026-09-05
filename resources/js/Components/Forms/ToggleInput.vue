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
    <fieldset class="fieldset">
        <legend class="fieldset-legend">{{ label }}</legend>
        <input
            v-bind="$attrs"
            :checked="resolvedValue"
            type="checkbox"
            class="toggle toggle-primary"
            :disabled="disabled"
            :aria-invalid="resolvedError ? 'true' : undefined"
            @change="onNativeChange"
        />
        <p
            v-if="resolvedError"
            class="text-error text-sm mt-1"
        >
            {{ resolvedError }}
        </p>
    </fieldset>
</template>
