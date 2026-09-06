import { onBeforeUnmount, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { readSpatieQuery } from './useSpatieTableQuery';
import { parseFilterValue } from '@/Components/DataTable/filterOperators';

export interface CalendarRange {
    from: string;
    to: string;
}

// Calendar-relevant filters — a value that carries an operator prefix other than exact-match
// ("!=", "<", "~", "between:" ...) is skipped: JobCalendarFilterData takes exact values only.
const PASS_THROUGH_FILTERS = ['status', 'cleaning_object_id', 'assigned_membership_id'] as const;

interface CalendarState {
    events: App.Data.Schedule.JobCalendarItemData[];
    loading: boolean;
    error: string | null;
    range: CalendarRange | null;
}

export function useJobCalendar() {
    const { t } = useI18n();

    const state = reactive<CalendarState>({
        events: [],
        loading: false,
        error: null,
        range: null,
    });

    let controller: AbortController | null = null;

    function buildUrl(range: CalendarRange): string {
        const params = new URLSearchParams({ from: range.from, to: range.to });
        const filters = readSpatieQuery().filters;

        PASS_THROUGH_FILTERS.forEach((property) => {
            const raw = filters[property];
            if (raw === undefined) return;
            const parsed = parseFilterValue(raw, '=');
            if (parsed.operator !== '=' || parsed.value === null) return;
            params.set(property, parsed.value);
        });

        return `/jobs/calendar?${params.toString()}`;
    }

    async function load(range: CalendarRange): Promise<void> {
        controller?.abort();
        controller = new AbortController();
        const currentController = controller;

        state.range = range;
        state.loading = true;
        state.error = null;

        try {
            const response = await fetch(buildUrl(range), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: currentController.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = (await response.json()) as App.Data.Schedule.JobCalendarItemData[];

            if (currentController.signal.aborted) return;

            state.events = payload;
            state.loading = false;
        } catch (err) {
            if (err instanceof DOMException && err.name === 'AbortError') {
                return;
            }
            state.loading = false;
            state.error = t('schedule_calendar_load_failed');
        }
    }

    async function reload(): Promise<void> {
        if (state.range) {
            await load(state.range);
        }
    }

    onBeforeUnmount(() => controller?.abort());

    return { state, load, reload };
}
