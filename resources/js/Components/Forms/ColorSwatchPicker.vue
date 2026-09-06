<script setup lang="ts">
import { computed } from 'vue';
import FormField from './FormField.vue';
import { useFormContext } from './useFormContext';
import { useFieldError, callValidate } from './useFieldError';
import type { TenantColorOption } from '@/types';

const props = defineProps<{
    field?: string;
    modelValue?: App.Enums.TenantColorEnum | null;
    colors: TenantColorOption[];
    label: string;
    error?: string | null;
    required?: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: App.Enums.TenantColorEnum | null];
}>();

const form = useFormContext();

const resolvedValue = computed<App.Enums.TenantColorEnum | null>(() => {
    if (props.field && form)
        return ((form as Record<string, unknown>)[props.field] as App.Enums.TenantColorEnum | null) ?? null;
    return props.modelValue ?? null;
});

const resolvedError = useFieldError(props, form);

function select(value: App.Enums.TenantColorEnum) {
    const next = resolvedValue.value === value ? null : value;
    if (props.field && form) {
        (form as Record<string, unknown>)[props.field] = next;
    }
    emit('update:modelValue', next);
    callValidate(form, props.field);
}
</script>

<template>
    <FormField :label="label" :error="resolvedError" :required="required">
        <div role="radiogroup" :aria-label="label" class="flex flex-wrap gap-2 pt-1">
            <button
                v-for="opt in colors"
                :key="opt.value"
                type="button"
                role="radio"
                :aria-checked="resolvedValue === opt.value"
                :aria-label="opt.label"
                :disabled="disabled"
                class="h-8 w-8 rounded-full border-2 transition focus:outline-none focus:ring-2 focus:ring-offset-1"
                :style="{ backgroundColor: opt.value }"
                :class="
                    resolvedValue === opt.value
                        ? 'border-base-content ring-2 ring-base-content ring-offset-1'
                        : 'border-transparent hover:border-base-300'
                "
                @click="select(opt.value)"
            />
        </div>
    </FormField>
</template>
