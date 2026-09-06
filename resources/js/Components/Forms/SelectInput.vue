<script setup lang="ts">
import { computed } from 'vue';
import FormField from './FormField.vue';
import { useFormContext } from './useFormContext';
import { useFieldError, callValidate } from './useFieldError';

defineOptions({ inheritAttrs: false });

export interface SelectOption {
    value: string | number;
    label: string;
}

const props = defineProps<{
    field?: string;
    modelValue?: string | number | null;
    label: string;
    options: SelectOption[];
    required?: boolean;
    error?: string | null;
    disabled?: boolean;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const form = useFormContext();

const resolvedValue = computed(() => {
    if (props.field && form) return String((form as Record<string, unknown>)[props.field] ?? '');
    return String(props.modelValue ?? '');
});

const resolvedError = useFieldError(props, form);

function onNativeChange(event: Event) {
    const val = (event.target as HTMLSelectElement).value;
    if (props.field && form) {
        (form as Record<string, unknown>)[props.field] = val;
    }
    callValidate(form, props.field);
    emit('update:modelValue', val);
}
</script>

<template>
    <FormField :label="label" :error="resolvedError" :required="required">
        <select
            v-bind="$attrs"
            :value="resolvedValue"
            class="select w-full"
            :class="{ 'select-error': resolvedError }"
            :required="required"
            :disabled="disabled"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="resolvedError ? 'true' : undefined"
            @change="onNativeChange"
        >
            <option v-if="placeholder" value="" disabled>
                {{ placeholder }}
            </option>
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
    </FormField>
</template>
