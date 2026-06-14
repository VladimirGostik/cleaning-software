import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export type InvoiceTab = 'all' | 'all_issued' | 'recurring' | 'drafts' | 'overdue';

export interface InvoiceFilterState {
    search: string;
    status: App.Enums.InvoiceStatusEnum | undefined;
    type: App.Enums.InvoiceTypeEnum | undefined;
    month: string | undefined;
    tab: InvoiceTab | undefined;
    issued_from: string | undefined;
    issued_to: string | undefined;
    due_from: string | undefined;
    due_to: string | undefined;
    total_min: number | null;
    total_max: number | null;
    client_id: string | undefined;
}

function tabToParams(tab: InvoiceTab | undefined): {
    status: App.Enums.InvoiceStatusEnum | undefined;
    type: App.Enums.InvoiceTypeEnum | undefined;
} {
    switch (tab) {
        case 'drafts':
            return { status: 'draft', type: undefined };
        case 'recurring':
            return { status: undefined, type: 'monthly' };
        case 'overdue':
            return { status: 'overdue', type: undefined };
        case 'all':
            return { status: undefined, type: undefined };
        case 'all_issued':
            // BE filters by ?tab=all_issued directly — no additional status/type param needed
            return { status: undefined, type: undefined };
        default:
            return { status: undefined, type: undefined };
    }
}

export function useInvoiceFilters(initial: {
    search?: string | null;
    status?: App.Enums.InvoiceStatusEnum | null;
    type?: App.Enums.InvoiceTypeEnum | null;
    month?: string | null;
    tab?: InvoiceTab | null;
    issued_from?: string | null;
    issued_to?: string | null;
    due_from?: string | null;
    due_to?: string | null;
    total_min?: number | string | null;
    total_max?: number | string | null;
    client_id?: string | null;
}) {
    const state = reactive<InvoiceFilterState>({
        search: initial.search ?? '',
        status: initial.status ?? undefined,
        type: initial.type ?? undefined,
        month: initial.month ?? undefined,
        tab: initial.tab ?? undefined,
        issued_from: initial.issued_from ?? undefined,
        issued_to: initial.issued_to ?? undefined,
        due_from: initial.due_from ?? undefined,
        due_to: initial.due_to ?? undefined,
        total_min: initial.total_min != null ? Number(initial.total_min) : null,
        total_max: initial.total_max != null ? Number(initial.total_max) : null,
        client_id: initial.client_id ?? undefined,
    });

    let timer: ReturnType<typeof setTimeout> | null = null;

    function apply() {
        const tabParams = tabToParams(state.tab);
        router.get(
            '/invoices',
            {
                search: state.search || undefined,
                status: state.tab ? tabParams.status : state.status,
                type: state.tab ? tabParams.type : state.type,
                month: state.month,
                tab: state.tab,
                issued_from: state.issued_from,
                issued_to: state.issued_to,
                due_from: state.due_from,
                due_to: state.due_to,
                total_min: state.total_min ?? undefined,
                total_max: state.total_max ?? undefined,
                client_id: state.client_id,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: [
                    'invoices',
                    'filters',
                    'tabCounts',
                    'invoiceStats',
                    'clients',
                    'statusOptions',
                    'typeOptions',
                    'invoiceSettings',
                    'settingsTemplateOptions',
                    'settingsCompanyName',
                    'settingsIsVatPayer',
                    'nextNumberPreview',
                ],
            },
        );
    }

    function applyDebounced() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(apply, 300);
    }

    watch(() => state.search, applyDebounced);
    watch(() => [state.status, state.type], apply);
    watch(() => [state.month, state.tab], apply);
    watch(() => [state.issued_from, state.issued_to, state.due_from, state.due_to, state.client_id], apply);
    watch(() => [state.total_min, state.total_max], applyDebounced);

    return { state, apply };
}
