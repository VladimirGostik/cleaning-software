<script setup lang="ts">
    import { computed } from 'vue';
    import FormField from './FormField.vue';
    import { useFormContext } from './useFormContext';
    import { useFieldError } from './useFieldError';
    import { callValidate } from './useFormContext';

    const props = withDefaults(
        defineProps<{
            field?: string;
            modelValue?: string;
            label?: string;
            type?: string;
            placeholder?: string;
            autocomplete?: string;
            ariaLabel?: string;
            required?: boolean;
            disabled?: boolean;
            error?: string;
            maxlength?: number;
        }>(),
        {
            field: undefined,
            modelValue: undefined,
            label: undefined,
            type: 'text',
            placeholder: undefined,
            autocomplete: undefined,
            ariaLabel: undefined,
            required: false,
            disabled: false,
            error: undefined,
            maxlength: undefined,
        },
    );

    const emit = defineEmits<{
        'update:modelValue': [value: string];
    }>();

    const form = useFormContext();
    const resolvedError = useFieldError(props, form);

    const resolvedValue = computed<string>(() => {
        if (props.field && form) {
            return ((form as unknown as Record<string, unknown>)[props.field] as string) ?? '';
        }
        return props.modelValue ?? '';
    });

    function onInput(event: Event): void {
        const value = (event.target as HTMLInputElement).value;
        if (props.field && form) {
            (form as unknown as Record<string, unknown>)[props.field] = value;
            callValidate(form, props.field);
        }
        emit('update:modelValue', value);
    }
</script>

<template>
    <FormField :label="label" :error="resolvedError" :required="required">
        <input
            :type="type"
            :value="resolvedValue"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :aria-label="ariaLabel"
            :required="required"
            :disabled="disabled"
            :maxlength="maxlength"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="resolvedError ? 'true' : undefined"
            class="input w-full"
            :class="{ 'input-error': resolvedError }"
            @input="onInput"
        />
    </FormField>
</template>
