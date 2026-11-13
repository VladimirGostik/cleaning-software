<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { format, isValid, parseISO } from 'date-fns';
import CalendarIcon from '~icons/material-symbols/calendar-today-outline-rounded';

import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import CalendarGrid from './CalendarGrid.vue';
import FormField from './FormField.vue';
import { parseTypedDate, toIsoDate, withinRange } from '@/utils/calendar';
import { DATE_FORMAT } from '@/utils/date';

const props = defineProps<{
    modelValue: string | null;
    label: string;
    labelClass?: string;
    required?: boolean;
    error?: string | null;
    min?: string;
    max?: string;
    disabled?: boolean;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const { t } = useI18n();

const open = ref(false);
const localError = ref<string | null>(null);

function show(value: string | null): string {
    if (!value) return '';
    const date = parseISO(value.slice(0, 10));

    return isValid(date) ? format(date, DATE_FORMAT) : '';
}

const iso = computed(() => props.modelValue?.slice(0, 10) ?? null);
const text = ref(show(props.modelValue));
const todayIso = computed(() => toIsoDate(new Date()));
const todayAllowed = computed(() => withinRange(todayIso.value, props.min, props.max));
const shownError = computed(() => props.error ?? localError.value);

let holdInvalid = false;

watch(
    () => props.modelValue,
    (value) => {
        if (holdInvalid) {
            holdInvalid = false;
            return;
        }

        text.value = show(value);
        localError.value = null;
    },
);

function rangeLabel(): string {
    if (props.min && props.max) return `${show(props.min)} – ${show(props.max)}`;

    return props.min ? `≥ ${show(props.min)}` : `≤ ${show(props.max ?? null)}`;
}

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

    const parsed = parseTypedDate(typed, new Date());
    if (parsed === null) return reject(t('date_input_invalid'));

    if (!withinRange(parsed, props.min, props.max)) {
        return reject(t('date_input_out_of_range', { range: rangeLabel() }));
    }

    localError.value = null;
    text.value = show(parsed);
    if (parsed !== iso.value) emit('update:modelValue', parsed);

    return true;
}

function onEnter(event: KeyboardEvent): void {
    if (!commit()) event.preventDefault();
}

function revert(): void {
    text.value = show(props.modelValue);
    localError.value = null;
}

function choose(value: string | null): void {
    localError.value = null;
    text.value = show(value);
    open.value = false;
    emit('update:modelValue', value);
}
</script>

<template>
    <FormField :label="label" :label-class="labelClass" :error="shownError" :required="required">
        <AnchoredPopover v-model:open="open" block class="w-86 max-w-[calc(100vw-1rem)] space-y-3 p-4">
            <template #trigger="{ toggle, open: isOpen }">
                <label class="input w-full" :class="{ 'input-error': shownError }">
                    <input
                        v-model="text"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        :aria-label="label"
                        :placeholder="placeholder ?? t('date_input_placeholder')"
                        :required="required"
                        :disabled="disabled"
                        :aria-required="required ? 'true' : undefined"
                        :aria-invalid="shownError ? 'true' : undefined"
                        @blur="commit"
                        @keydown.enter="onEnter"
                        @keydown.esc="revert"
                    />
                    <button
                        type="button"
                        class="btn btn-ghost btn-square btn-sm"
                        :disabled="disabled"
                        aria-haspopup="dialog"
                        :aria-expanded="isOpen"
                        :aria-label="t('date_picker_open', { label })"
                        @click="toggle"
                    >
                        <CalendarIcon aria-hidden="true" />
                    </button>
                </label>
            </template>

            <div role="dialog" :aria-label="label" class="space-y-3">
                <CalendarGrid :model-value="iso" :label="label" :min="min" :max="max" @update:model-value="choose" />
                <div class="flex items-center justify-between gap-2">
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm"
                        :disabled="!todayAllowed"
                        @click="choose(todayIso)"
                    >
                        {{ t('date_picker_today') }}
                    </button>
                    <button v-if="!required" type="button" class="btn btn-ghost btn-sm" @click="choose(null)">
                        {{ t('date_picker_clear') }}
                    </button>
                </div>
            </div>
        </AnchoredPopover>
    </FormField>
</template>
