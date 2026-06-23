import { reactive, watch, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

export type CalendarView = 'dayGridMonth' | 'timeGridWeek';
export type ViewMode = 'list' | 'calendar';

export interface JobFilterState {
    search: string;
    status: App.Enums.JobStatusEnum | undefined;
    type: App.Enums.JobTypeEnum | undefined;
    date_from: string | undefined;
    date_to: string | undefined;
}

export interface JobUiState {
    viewMode: ViewMode;
    calendarView: CalendarView;
}

export function useJobFilters(initial: {
    search?: string | null;
    status?: App.Enums.JobStatusEnum | null;
    type?: App.Enums.JobTypeEnum | null;
    date_from?: string | null;
    date_to?: string | null;
}) {
    const state = reactive<JobFilterState>({
        search: initial.search ?? '',
        status: initial.status ?? undefined,
        type: initial.type ?? undefined,
        date_from: initial.date_from ?? undefined,
        date_to: initial.date_to ?? undefined,
    });

    const ui = reactive<JobUiState>({
        viewMode: 'list',
        calendarView: 'dayGridMonth',
    });

    let timer: ReturnType<typeof setTimeout> | null = null;

    function apply(): void {
        router.get(
            '/jobs',
            {
                search: state.search || undefined,
                status: state.status,
                type: state.type,
                date_from: state.date_from,
                date_to: state.date_to,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['jobs', 'filters'],
            },
        );
    }

    function applyDebounced(): void {
        if (timer) clearTimeout(timer);
        timer = setTimeout(apply, 300);
    }

    watch(() => state.search, applyDebounced);
    watch(() => [state.status, state.type, state.date_from, state.date_to], apply);

    onUnmounted(() => {
        if (timer) clearTimeout(timer);
    });

    return { state, ui, apply };
}
