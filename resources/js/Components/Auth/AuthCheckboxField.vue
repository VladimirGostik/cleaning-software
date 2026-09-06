<script setup lang="ts">
import { computed } from 'vue';
import { CheckIcon } from '@heroicons/vue/24/outline';
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
    <label class="flex cursor-pointer items-center gap-2">
        <span
            class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded border transition"
            :class="resolvedValue ? 'auth-checkbox-checked' : 'auth-checkbox-unchecked'"
            aria-hidden="true"
        >
            <CheckIcon v-if="resolvedValue" class="h-3 w-3" />
        </span>
        <input type="checkbox" class="sr-only" :checked="resolvedValue" @change="onNativeChange" />
        <span class="text-[13px] text-slate-700">{{ label }}</span>
    </label>
</template>
