import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export interface InvoiceFilterState {
    search: string;
    status: App.Enums.InvoiceStatusEnum | undefined;
    type: App.Enums.InvoiceTypeEnum | undefined;
}

export function useInvoiceFilters(initial: {
    search?: string | null;
    status?: App.Enums.InvoiceStatusEnum | null;
    type?: App.Enums.InvoiceTypeEnum | null;
}) {
    const state = reactive<InvoiceFilterState>({
        search: initial.search ?? '',
        status: initial.status ?? undefined,
        type: initial.type ?? undefined,
    });

    let timer: ReturnType<typeof setTimeout> | null = null;

    function apply() {
        router.get(
            '/invoices',
            {
                search: state.search || undefined,
                status: state.status,
                type: state.type,
            },
            { preserveState: true, preserveScroll: true, replace: true, only: ['invoices', 'filters'] },
        );
    }

    function applyDebounced() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(apply, 300);
    }

    watch(() => state.search, applyDebounced);
    watch(() => [state.status, state.type], apply);

    return { state, apply };
}
