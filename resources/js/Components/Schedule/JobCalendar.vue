<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import type { CalendarOptions, EventClickArg, EventInput, EventMountArg, DatesSetArg } from '@fullcalendar/core';
import skLocale from '@fullcalendar/core/locales/sk';
import ukLocale from '@fullcalendar/core/locales/uk';

import { subDays, format } from 'date-fns';
import { usePageProps } from '@/Composables/usePageProps';
import type { CalendarRange } from '@/Composables/useJobCalendar';
import { jobStatusColors } from './jobStatusStyle';

const props = defineProps<{
    events: readonly App.Data.Schedule.JobCalendarItemData[];
    loading: boolean;
    error: string | null;
}>();

const emit = defineEmits<{
    datesSet: [range: CalendarRange];
    retry: [];
}>();

const { t } = useI18n();
const pageProps = usePageProps();

const fcEvents = computed<EventInput[]>(() =>
    props.events.map((job) => {
        const colors = jobStatusColors(job.status);

        return {
            id: job.id,
            title: job.assignee_display_name ? `${job.object_name} · ${job.assignee_display_name}` : job.object_name,
            start: job.start_time ? `${job.scheduled_date}T${job.start_time}` : job.scheduled_date,
            end: job.end_time ? `${job.scheduled_date}T${job.end_time}` : undefined,
            allDay: !job.start_time,
            backgroundColor: colors.bg,
            borderColor: colors.bg,
            textColor: colors.fg,
        };
    }),
);

function onDatesSet(info: DatesSetArg): void {
    const from = info.startStr.slice(0, 10);
    const to = format(subDays(info.end, 1), 'yyyy-MM-dd');
    emit('datesSet', { from, to });
}

function onEventClick(info: EventClickArg): void {
    router.visit(`/jobs/${info.event.id}`);
}

function onEventDidMount(info: EventMountArg): void {
    info.el.title = info.event.title;
}

const STATIC_OPTIONS: CalendarOptions = {
    plugins: [dayGridPlugin, timeGridPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
    firstDay: 1,
    height: 'auto',
    editable: false,
    selectable: false,
    dayMaxEvents: true,
    slotMinTime: '06:00:00',
    slotMaxTime: '22:00:00',
    locales: [skLocale, ukLocale],
    eventDisplay: 'block',
};

const calendarOptions = computed<CalendarOptions>(() => ({
    ...STATIC_OPTIONS,
    events: fcEvents.value,
    locale: pageProps.value.locale,
    datesSet: onDatesSet,
    eventClick: onEventClick,
    eventDidMount: onEventDidMount,
}));
</script>

<template>
    <div class="relative" :aria-busy="loading">
        <div v-if="error" class="alert alert-error mb-4">
            <span>{{ error }}</span>
            <button type="button" class="btn btn-sm" @click="emit('retry')">{{ t('retry') }}</button>
        </div>

        <div class="fc-wrapper">
            <FullCalendar :options="calendarOptions" />

            <div v-if="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-base-100/60">
                <span class="loading loading-spinner" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.fc-wrapper {
    position: relative;
}

:deep(.fc) {
    --fc-border-color: var(--color-base-300);
    --fc-page-bg-color: var(--color-base-100);
    --fc-neutral-bg-color: var(--color-base-200);
    --fc-today-bg-color: color-mix(in oklch, var(--color-primary) 4%, transparent);
    --fc-button-bg-color: var(--color-neutral);
    --fc-button-border-color: var(--color-neutral);
    --fc-button-text-color: var(--color-base-content);
    --fc-button-hover-bg-color: var(--color-base-300);
    --fc-button-hover-border-color: var(--color-base-300);
    --fc-button-active-bg-color: var(--color-primary-soft);
    --fc-button-active-border-color: var(--color-primary-soft);
}
:deep(.fc .fc-toolbar-title) {
    font-size: 20px;
    font-weight: 600;
    letter-spacing: -0.02em;
}
:deep(.fc .fc-button) {
    height: 36px;
    padding: 0 12px;
    border-radius: var(--radius-field);
    font-size: 14px;
    font-weight: 500;
    box-shadow: none;
}
:deep(.fc .fc-button:focus-visible) {
    box-shadow: 0 0 0 3px var(--color-primary-soft);
}
:deep(.fc .fc-button-group > .fc-button:not(:first-child)) {
    border-start-start-radius: 0;
    border-end-start-radius: 0;
    margin-inline-start: 1px;
}
:deep(.fc .fc-button-group > .fc-button:not(:last-child)) {
    border-start-end-radius: 0;
    border-end-end-radius: 0;
}
:deep(.fc .fc-button-primary:not(:disabled).fc-button-active),
:deep(.fc .fc-button-primary:not(:disabled):active) {
    color: var(--color-primary-hover);
}
:deep(.fc .fc-event) {
    border: 0;
    border-radius: 6px;
    padding: 2px 6px;
}
</style>
