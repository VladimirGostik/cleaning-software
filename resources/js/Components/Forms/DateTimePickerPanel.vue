<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import CalendarGrid from './CalendarGrid.vue';
import TimeInput from './TimeInput.vue';
import { DEFAULT_DUE_TIME, nextMondayOf, toIsoDate, tomorrowOf } from '@/utils/calendar';
import { joinLocalDatetime } from '@/utils/date';

const props = defineProps<{
    modelValue: string;
    label: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    commit: [value: string];
    clear: [];
}>();

const { t } = useI18n();

const datePart = computed(() => props.modelValue.slice(0, 10) || null);
const timePart = computed(() => props.modelValue.slice(11, 16) || null);

function withDate(date: string): string {
    return joinLocalDatetime(date, timePart.value ?? DEFAULT_DUE_TIME);
}

function setTime(time: string | null): void {
    if (!datePart.value) return;
    emit('update:modelValue', joinLocalDatetime(datePart.value, time ?? DEFAULT_DUE_TIME));
}

function quick(date: string): void {
    emit('commit', withDate(date));
}
</script>

<template>
    <div class="space-y-3">
        <CalendarGrid
            :model-value="datePart"
            :label="label"
            @update:model-value="emit('update:modelValue', withDate($event))"
        />
        <TimeInput
            :model-value="timePart"
            :label="t('date_picker_time')"
            :disabled="!datePart"
            @update:model-value="setTime"
        />
        <div class="flex flex-wrap items-center gap-1">
            <button type="button" class="btn btn-ghost btn-sm" @click="quick(toIsoDate(new Date()))">
                {{ t('date_picker_today') }}
            </button>
            <button type="button" class="btn btn-ghost btn-sm" @click="quick(tomorrowOf(new Date()))">
                {{ t('date_picker_tomorrow') }}
            </button>
            <button type="button" class="btn btn-ghost btn-sm" @click="quick(nextMondayOf(new Date()))">
                {{ t('date_picker_next_monday') }}
            </button>
            <button v-if="modelValue" type="button" class="btn btn-ghost btn-sm ms-auto" @click="emit('clear')">
                {{ t('date_picker_clear') }}
            </button>
        </div>
    </div>
</template>
