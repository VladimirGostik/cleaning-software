<script setup lang="ts">
import { computed } from 'vue';
import FormField from './FormField.vue';
import { useFormContext } from './useFormContext';
import { useFieldError, callValidate } from './useFieldError';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        field?: string;
        modelValue?: string;
        label: string;
        type?: 'text' | 'email' | 'url' | 'tel' | 'search';
        required?: boolean;
        error?: string | null;
        autocomplete?: string;
        disabled?: boolean;
        placeholder?: string;
    }>(),
    { type: 'text' },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const form = useFormContext();

const resolvedValue = computed(() => {
    if (props.field && form) return String((form as Record<string, unknown>)[props.field] ?? '');
    return props.modelValue ?? '';
});

const resolvedError = useFieldError(props, form);

function onInput(event: Event) {
    const val = (event.target as HTMLInputElement).value;
    if (props.field && form) {
        (form as Record<string, unknown>)[props.field] = val;
    }
    emit('update:modelValue', val);
}

function onNativeChange() {
    callValidate(form, props.field);
}
</script>

<template>
    <FormField
        :label="label"
        :error="resolvedError"
        :required="required"
    >
        <input
            v-bind="$attrs"
            :value="resolvedValue"
            :type="type"
            class="input w-full"
            :class="{ 'input-error': resolvedError }"
            :required="required"
            :autocomplete="autocomplete"
            :disabled="disabled"
            :placeholder="placeholder"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="resolvedError ? 'true' : undefined"
            @input="onInput"
            @change="onNativeChange"
        />
    </FormField>
</template>
