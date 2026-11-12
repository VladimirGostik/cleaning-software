<script setup lang="ts">
import { computed } from 'vue';
import CheckIcon from '~icons/material-symbols/check-outline-rounded';
import { useFormContext } from '@/Components/Forms/useFormContext';
import { callValidate } from '@/Components/Forms/useFieldError';

const props = defineProps<{
    field: string;
    label: string;
}>();

const form = useFormContext();

const resolvedValue = computed(() => Boolean((form as Record<string, unknown> | null)?.[props.field]));

function onNativeChange(event: Event) {
    const checked = (event.target as HTMLInputElement).checked;
    if (form) {
        (form as Record<string, unknown>)[props.field] = checked;
    }
    callValidate(form, props.field);
}
</script>

<template>
    <label class="flex min-h-11 cursor-pointer items-center gap-2">
        <span
            class="flex size-6 shrink-0 items-center justify-center rounded-full transition"
            :class="resolvedValue ? 'auth-checkbox-checked' : 'auth-checkbox-unchecked'"
            aria-hidden="true"
        >
            <CheckIcon v-if="resolvedValue" />
        </span>
        <input type="checkbox" class="sr-only" :checked="resolvedValue" @change="onNativeChange" />
        <span class="text-xs text-base-content">{{ label }}</span>
    </label>
</template>
