<script setup lang="ts">
    import { computed, ref, watch } from 'vue';
    import FullCalendar from '@fullcalendar/vue3';
    import dayGridPlugin from '@fullcalendar/daygrid';
    import timeGridPlugin from '@fullcalendar/timegrid';
    import interactionPlugin from '@fullcalendar/interaction';
    import type { CalendarOptions, EventClickArg, DatesSetArg } from '@fullcalendar/core';
    import { router } from '@inertiajs/vue3';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { CalendarView } from '@/Composables/useJobFilters';

    const props = defineProps<{
        jobs: App.Data.Schedule.JobListItemData[];
        view: CalendarView;
    }>();

    const emit = defineEmits<{
        (e: 'datesSet', dateFrom: string, dateTo: string): void;
    }>();

    const { t } = useTranslate();

    // eslint-disable-next-line no-restricted-syntax -- calendar instance ref required for FullCalendar imperative API (changeView)
    const calendarRef = ref<InstanceType<typeof FullCalendar> | null>(null);

    const STATUS_BG: Record<App.Enums.JobStatusEnum, string> = {
        planned: 'var(--color-info)',
        unassigned: 'var(--color-warning)',
        in_progress: 'var(--color-primary)',
        completed: 'var(--color-success)',
        unapproved: 'var(--color-error)',
        cancelled: 'var(--color-neutral)',
    };

    const events = computed(() =>
        props.jobs.map((job) => {
            const color = STATUS_BG[job.status] ?? 'var(--color-neutral)';
            const title = job.assignee_display_name
                ? `${job.object_name} · ${job.assignee_display_name}`
                : job.object_name;

            return {
                id: job.id,
                title,
                start: job.start_time ? `${job.scheduled_date}T${job.start_time}` : job.scheduled_date,
                end: job.end_time ? `${job.scheduled_date}T${job.end_time}` : undefined,
                allDay: !job.start_time,
                backgroundColor: color,
                borderColor: color,
                extendedProps: { jobId: job.id },
            };
        }),
    );

    function handleEventClick(info: EventClickArg): void {
        router.visit(`/jobs/${String(info.event.extendedProps.jobId)}`);
    }

    function handleDatesSet(info: DatesSetArg): void {
        const from = info.startStr.slice(0, 10);
        const to = info.endStr.slice(0, 10);
        emit('datesSet', from, to);
    }

    const calendarOptions = computed<CalendarOptions>(() => ({
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
        initialView: props.view,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: '',
        },
        buttonText: {
            today: t('schedule.calendar.today'),
        },
        events: events.value,
        editable: false,
        selectable: false,
        eventClick: handleEventClick,
        datesSet: handleDatesSet,
        height: 'auto',
    }));

    watch(
        () => props.view,
        (newView) => {
            calendarRef.value?.getApi().changeView(newView);
        },
    );
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <FullCalendar ref="calendarRef" :options="calendarOptions" />
        </div>
    </div>
</template>
