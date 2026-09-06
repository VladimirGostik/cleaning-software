<script setup lang="ts">
import { computed, useId, type Component } from 'vue';
import { useFormContext } from '@/Components/Forms/useFormContext';
import { useFieldError, callValidate } from '@/Components/Forms/useFieldError';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        field: string;
        label: string;
        type?: 'text' | 'email';
        autocomplete?: string;
        required?: boolean;
        icon?: Component;
    }>(),
    { type: 'text' },
);

const id = useId();
const form = useFormContext();

const resolvedValue = computed(() => String((form as Record<string, unknown> | null)?.[props.field] ?? ''));
const resolvedError = useFieldError(props, form);

function onInput(event: Event) {
    const val = (event.target as HTMLInputElement).value;
    if (form) {
        (form as Record<string, unknown>)[props.field] = val;
    }
}

function onNativeChange() {
    callValidate(form, props.field);
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="id" class="text-sm font-medium text-slate-700">
            {{ label }}
        </label>
        <div class="relative">
            <component
                :is="icon"
                v-if="icon"
                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
            />
            <input
                :id="id"
                v-bind="$attrs"
                :value="resolvedValue"
                :type="type"
                :required="required"
                :autocomplete="autocomplete"
                :aria-invalid="resolvedError ? 'true' : undefined"
                :aria-describedby="resolvedError ? `${id}-error` : undefined"
                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pr-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                :class="[
                    icon ? 'pl-9' : 'pl-3',
                    { 'border-red-400 focus:border-red-400 focus:ring-red-100': resolvedError },
                ]"
                @input="onInput"
                @change="onNativeChange"
            />
        </div>
        <p v-if="resolvedError" :id="`${id}-error`" class="text-xs text-red-500">
            {{ resolvedError }}
        </p>
    </div>
</template>
