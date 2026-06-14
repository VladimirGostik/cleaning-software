import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export interface RecurringInvoiceFilterState {
    search: string;
    status: App.Enums.RecurringInvoiceStatusEnum | undefined;
    frequency: App.Enums.RecurringFrequencyEnum | undefined;
    client_id: string | undefined;
}

export function useRecurringInvoiceFilters(initial: {
    search?: string | null;
    status?: App.Enums.RecurringInvoiceStatusEnum | null;
    frequency?: App.Enums.RecurringFrequencyEnum | null;
    client_id?: string | null;
}) {
    const state = reactive<RecurringInvoiceFilterState>({
        search: initial.search ?? '',
        status: initial.status ?? undefined,
        frequency: initial.frequency ?? undefined,
        client_id: initial.client_id ?? undefined,
    });

    let timer: ReturnType<typeof setTimeout> | null = null;

    function apply(): void {
        router.get(
            '/recurring-invoices',
            {
                search: state.search || undefined,
                status: state.status,
                frequency: state.frequency,
                client_id: state.client_id,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['recurringInvoices', 'filters', 'statusOptions', 'frequencyOptions', 'clients'],
            },
        );
    }

    function applyDebounced(): void {
        if (timer) clearTimeout(timer);
        timer = setTimeout(apply, 300);
    }

    watch(() => state.search, applyDebounced);
    watch(() => [state.status, state.frequency, state.client_id], apply);

    return { state, apply };
}
