<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { addMonths, format, isSameDay, isSameMonth, startOfMonth, subMonths } from 'date-fns';
import ChevronLeftIcon from '~icons/material-symbols/chevron-left-outline-rounded';
import ChevronRightIcon from '~icons/material-symbols/chevron-right-outline-rounded';

import { fromIsoDate, monthMatrix, moveFocus, toIsoDate, withinRange } from '@/utils/calendar';
import { localeTag } from '@/utils/money';

const props = defineProps<{
    modelValue: string | null;
    label: string;
    min?: string;
    max?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { t, locale } = useI18n();

const today = new Date();
const selected = computed(() => (props.modelValue ? fromIsoDate(props.modelValue) : null));
const viewMonth = ref(startOfMonth(selected.value ?? today));
const focusDay = ref(selected.value ?? today);
const grid = ref<HTMLElement | null>(null);

watch(
    () => props.modelValue,
    (value) => {
        if (!value) return;
        const date = fromIsoDate(value);
        viewMonth.value = startOfMonth(date);
        focusDay.value = date;
    },
);

const tag = computed(() => localeTag(locale.value));
const monthTitle = computed(() =>
    new Intl.DateTimeFormat(tag.value, { month: 'long', year: 'numeric' }).format(viewMonth.value),
);
const weeks = computed(() => monthMatrix(viewMonth.value));
const weekdays = computed(() => {
    const short = new Intl.DateTimeFormat(tag.value, { weekday: 'short' });
    const long = new Intl.DateTimeFormat(tag.value, { weekday: 'long' });

    return (weeks.value[0] ?? []).map((day) => ({ short: short.format(day), long: long.format(day) }));
});
const tabbable = computed(() => (isSameMonth(focusDay.value, viewMonth.value) ? focusDay.value : viewMonth.value));

function isDisabled(day: Date): boolean {
    return !withinRange(toIsoDate(day), props.min, props.max);
}

function dayLabel(day: Date): string {
    return new Intl.DateTimeFormat(tag.value, { dateStyle: 'full' }).format(day);
}

function pick(day: Date): void {
    if (isDisabled(day)) return;
    focusDay.value = day;
    emit('update:modelValue', toIsoDate(day));
}

function shiftMonth(step: 1 | -1): void {
    viewMonth.value = step === 1 ? addMonths(viewMonth.value, 1) : subMonths(viewMonth.value, 1);
}

async function onKeydown(event: KeyboardEvent, day: Date): Promise<void> {
    const next = moveFocus(day, event.key, event.shiftKey);
    if (next === day) return;

    event.preventDefault();
    focusDay.value = next;
    viewMonth.value = startOfMonth(next);
    await nextTick();
    grid.value?.querySelector<HTMLElement>(`[data-day="${toIsoDate(next)}"]`)?.focus();
}
</script>

<template>
    <div class="w-full">
        <div class="flex items-center justify-between gap-2 pb-2">
            <button
                type="button"
                class="btn btn-ghost btn-square btn-sm"
                :aria-label="t('date_picker_prev_month')"
                @click="shiftMonth(-1)"
            >
                <ChevronLeftIcon aria-hidden="true" />
            </button>
            <span class="text-sm font-medium capitalize" aria-live="polite">{{ monthTitle }}</span>
            <button
                type="button"
                class="btn btn-ghost btn-square btn-sm"
                :aria-label="t('date_picker_next_month')"
                @click="shiftMonth(1)"
            >
                <ChevronRightIcon aria-hidden="true" />
            </button>
        </div>

        <div ref="grid" role="grid" :aria-label="label">
            <div role="row" class="grid grid-cols-7">
                <div
                    v-for="weekday in weekdays"
                    :key="weekday.long"
                    role="columnheader"
                    :abbr="weekday.long"
                    class="py-1 text-center text-xs text-muted"
                >
                    {{ weekday.short }}
                </div>
            </div>
            <div v-for="(week, index) in weeks" :key="index" role="row" class="grid grid-cols-7">
                <div
                    v-for="day in week"
                    :key="toIsoDate(day)"
                    role="gridcell"
                    :aria-selected="selected !== null && isSameDay(day, selected)"
                >
                    <button
                        type="button"
                        class="kg-calendar-day"
                        :class="{
                            'kg-calendar-day--selected': selected !== null && isSameDay(day, selected),
                            'kg-calendar-day--today': isSameDay(day, today),
                            'kg-calendar-day--outside': !isSameMonth(day, viewMonth),
                        }"
                        :data-day="toIsoDate(day)"
                        :data-autofocus="isSameDay(day, tabbable) ? '' : undefined"
                        :tabindex="isSameDay(day, tabbable) ? 0 : -1"
                        :aria-label="dayLabel(day)"
                        :aria-current="isSameDay(day, today) ? 'date' : undefined"
                        :aria-disabled="isDisabled(day) ? 'true' : undefined"
                        @click="pick(day)"
                        @keydown="onKeydown($event, day)"
                    >
                        {{ format(day, 'd') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
