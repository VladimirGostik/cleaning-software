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
import { JOB_STATUS_STYLE } from './jobStatusStyle';

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
        const style = JOB_STATUS_STYLE[job.status];

        return {
            id: job.id,
            title: job.assignee_display_name ? `${job.object_name} · ${job.assignee_display_name}` : job.object_name,
            start: job.start_time ? `${job.scheduled_date}T${job.start_time}` : job.scheduled_date,
            end: job.end_time ? `${job.scheduled_date}T${job.end_time}` : undefined,
            allDay: !job.start_time,
            backgroundColor: style.color,
            borderColor: style.color,
            textColor: style.text,
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

const calendarOptions = computed<CalendarOptions>(() => ({
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
    events: fcEvents.value,
    locales: [skLocale, ukLocale],
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
    --fc-button-bg-color: var(--color-primary);
    --fc-button-border-color: var(--color-primary);
    --fc-button-hover-bg-color: color-mix(in oklch, var(--color-primary) 80%, black);
    --fc-button-active-bg-color: var(--color-primary);
    --fc-today-bg-color: color-mix(in oklch, var(--color-primary) 10%, transparent);
    --fc-button-text-color: var(--color-primary-content);
}
</style>
