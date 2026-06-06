import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export interface ClientFilterState {
    search: string;
    type: App.Enums.ClientTypeEnum | undefined;
}

export function useClientFilters(initial: { search?: string | null; type?: App.Enums.ClientTypeEnum | null }) {
    const state = reactive<ClientFilterState>({
        search: initial.search ?? '',
        type: initial.type ?? undefined,
    });

    let timer: ReturnType<typeof setTimeout> | null = null;

    function apply() {
        router.get(
            '/clients',
            {
                search: state.search || undefined,
                type: state.type,
            },
            { preserveState: true, preserveScroll: true, replace: true, only: ['clients', 'filters'] },
        );
    }

    function applyDebounced() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(apply, 300);
    }

    watch(() => state.search, applyDebounced);
    watch(() => state.type, apply);

    return { state, apply };
}
