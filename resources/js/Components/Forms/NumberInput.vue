<script setup lang="ts">
    import { computed } from 'vue';
    import FormField from './FormField.vue';
    import { useFormContext, callValidate } from './useFormContext';
    import { useFieldError } from './useFieldError';

    const props = withDefaults(
        defineProps<{
            field?: string;
            modelValue?: number | null;
            label?: string;
            placeholder?: string;
            min?: number;
            max?: number;
            step?: number;
            required?: boolean;
            disabled?: boolean;
            error?: string;
        }>(),
        {
            field: undefined,
            modelValue: null,
            label: undefined,
            placeholder: undefined,
            min: undefined,
            max: undefined,
            step: undefined,
            required: false,
            disabled: false,
            error: undefined,
        },
    );

    const emit = defineEmits<{
        'update:modelValue': [value: number | null];
    }>();

    const form = useFormContext();
    const resolvedError = useFieldError(props, form);

    const resolvedValue = computed<string>(() => {
        const v =
            props.field && form
                ? ((form as unknown as Record<string, unknown>)[props.field] as number | null | undefined)
                : props.modelValue;
        return v === null || v === undefined ? '' : String(v);
    });

    function onInput(event: Event): void {
        const raw = (event.target as HTMLInputElement).value;
        const parsed = raw === '' ? null : Number(raw);
        const value = Number.isNaN(parsed) ? null : parsed;
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
            type="number"
            :value="resolvedValue"
            :placeholder="placeholder"
            :min="min"
            :max="max"
            :step="step"
            :required="required"
            :disabled="disabled"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="resolvedError ? 'true' : undefined"
            class="input w-full"
            :class="{ 'input-error': resolvedError }"
            @input="onInput"
        />
    </FormField>
</template>
