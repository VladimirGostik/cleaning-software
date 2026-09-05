import { reactive, watch, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

export interface QuoteFilterState {
    search: string;
    status: App.Enums.QuoteStatusEnum | undefined;
    client_id: string | undefined;
    kind: App.Enums.QuoteKindEnum | undefined;
    valid_from: string | undefined;
    valid_to: string | undefined;
}

export function useQuoteFilters(initial: {
    search?: string | null;
    status?: App.Enums.QuoteStatusEnum | null;
    client_id?: string | null;
    kind?: App.Enums.QuoteKindEnum | null;
    valid_from?: string | null;
    valid_to?: string | null;
}) {
    const state = reactive<QuoteFilterState>({
        search: initial.search ?? '',
        status: initial.status ?? undefined,
        client_id: initial.client_id ?? undefined,
        kind: initial.kind ?? undefined,
        valid_from: initial.valid_from ?? undefined,
        valid_to: initial.valid_to ?? undefined,
    });

    let timer: ReturnType<typeof setTimeout> | null = null;

    function apply() {
        router.get(
            '/quotes',
            {
                search: state.search || undefined,
                status: state.status,
                client_id: state.client_id,
                kind: state.kind,
                valid_from: state.valid_from,
                valid_to: state.valid_to,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['quotes', 'filters', 'statusOptions', 'clients'],
            },
        );
    }

    function applyDebounced() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(apply, 300);
    }

    watch(() => state.search, applyDebounced);
    watch(() => [state.status, state.client_id, state.kind, state.valid_from, state.valid_to], apply);

    onUnmounted(() => {
        if (timer) clearTimeout(timer);
    });

    return { state, apply };
}
