<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import FormField from './FormField.vue';
import { parseTypedTime, withinRange } from '@/utils/calendar';

const props = defineProps<{
    modelValue: string | null;
    label: string;
    required?: boolean;
    error?: string | null;
    min?: string;
    max?: string;
    step?: number;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const { t } = useI18n();

const shown = (value: string | null): string => value?.slice(0, 5) ?? '';
const text = ref(shown(props.modelValue));
const localError = ref<string | null>(null);

let holdInvalid = false;

watch(
    () => props.modelValue,
    (value) => {
        if (holdInvalid) {
            holdInvalid = false;
            return;
        }

        text.value = shown(value);
        localError.value = null;
    },
);

function reject(message: string): false {
    localError.value = message;
    holdInvalid = props.modelValue !== null;
    if (props.modelValue !== null) emit('update:modelValue', null);

    return false;
}

function commit(): boolean {
    const typed = text.value.trim();

    if (typed === '') {
        localError.value = null;
        if (props.modelValue !== null) emit('update:modelValue', null);
        return true;
    }

    const parsed = parseTypedTime(typed);
    if (parsed === null) return reject(t('date_picker_time_invalid'));

    if (!withinRange(parsed, props.min?.slice(0, 5), props.max?.slice(0, 5))) {
        return reject(t('time_input_out_of_range'));
    }

    localError.value = null;
    text.value = parsed;
    if (parsed !== shown(props.modelValue)) emit('update:modelValue', parsed);

    return true;
}

function onEnter(event: KeyboardEvent): void {
    if (!commit()) event.preventDefault();
}

function revert(): void {
    text.value = shown(props.modelValue);
    localError.value = null;
}
</script>

<template>
    <FormField :label="label" :error="error ?? localError" :required="required">
        <input
            v-model="text"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            class="input w-full"
            :class="{ 'input-error': error ?? localError }"
            :placeholder="t('time_input_placeholder')"
            :required="required"
            :disabled="disabled"
            :aria-required="required ? 'true' : undefined"
            :aria-invalid="(error ?? localError) ? 'true' : undefined"
            @blur="commit"
            @keydown.enter="onEnter"
            @keydown.esc="revert"
        />
    </FormField>
</template>
