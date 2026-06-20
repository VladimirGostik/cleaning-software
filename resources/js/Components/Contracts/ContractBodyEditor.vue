<script setup lang="ts">
    import { computed } from 'vue';

    import { ref } from 'vue';
    import FormField from '@/Components/Forms/FormField.vue';
    import { useFormContext, callValidate } from '@/Components/Forms/useFormContext';
    import { useFieldError } from '@/Components/Forms/useFieldError';

    const props = withDefaults(
        defineProps<{
            field?: string;
            modelValue?: string;
            label?: string;
            error?: string;
            required?: boolean;
            disabled?: boolean;
            rows?: number;
            placeholder?: string;
        }>(),
        {
            field: undefined,
            modelValue: undefined,
            label: undefined,
            error: undefined,
            required: false,
            disabled: false,
            rows: 20,
            placeholder: undefined,
        },
    );

    const emit = defineEmits<{
        'update:modelValue': [string];
    }>();

    const form = useFormContext();
    const resolvedError = useFieldError(props, form);

    // eslint-disable-next-line no-restricted-syntax -- ref needed for direct textarea DOM access (cursor positioning)
    const textareaRef = ref<HTMLTextAreaElement | null>(null);

    const resolvedValue = computed<string>(() => {
        if (props.field && form) {
            return ((form as unknown as Record<string, unknown>)[props.field] as string) ?? '';
        }
        return props.modelValue ?? '';
    });

    function onInput(event: Event): void {
        const value = (event.target as HTMLTextAreaElement).value;
        if (props.field && form) {
            (form as unknown as Record<string, unknown>)[props.field] = value;
            callValidate(form, props.field);
        }
        emit('update:modelValue', value);
    }

    function insertAtCursor(token: string): void {
        const el = textareaRef.value;
        if (!el) {
            return;
        }

        const start = el.selectionStart ?? resolvedValue.value.length;
        const end = el.selectionEnd ?? start;
        const current = resolvedValue.value;
        const updated = current.slice(0, start) + token + current.slice(end);

        if (props.field && form) {
            (form as unknown as Record<string, unknown>)[props.field] = updated;
        }
        emit('update:modelValue', updated);

        requestAnimationFrame(() => {
            el.focus();
            const cursorPos = start + token.length;
            el.setSelectionRange(cursorPos, cursorPos);
        });
    }

    defineExpose({ insertAtCursor });
</script>

<template>
    <FormField :label="label" :error="resolvedError" :required="required">
        <textarea
            ref="textareaRef"
            :value="resolvedValue"
            :rows="rows"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="resolvedError ? 'true' : undefined"
            class="textarea textarea-bordered w-full font-mono text-sm"
            :class="{ 'textarea-error': resolvedError }"
            @input="onInput"
        />
    </FormField>
</template>
