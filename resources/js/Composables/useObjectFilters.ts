import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export interface ObjectFilterState {
    search: string;
    type: App.Enums.ObjectTypeEnum | undefined;
    client_id: string | undefined;
    is_active: boolean | undefined;
}

export function useObjectFilters(initial: {
    search?: string | null;
    type?: App.Enums.ObjectTypeEnum | null;
    client_id?: string | null;
    is_active?: boolean | null;
}) {
    const state = reactive<ObjectFilterState>({
        search: initial.search ?? '',
        type: initial.type ?? undefined,
        client_id: initial.client_id ?? undefined,
        is_active: initial.is_active ?? undefined,
    });

    let timer: ReturnType<typeof setTimeout> | null = null;

    function apply() {
        router.get(
            '/objects',
            {
                search: state.search || undefined,
                type: state.type,
                client_id: state.client_id,
                is_active: state.is_active,
            },
            { preserveState: true, preserveScroll: true, replace: true, only: ['objects', 'filters'] },
        );
    }

    function applyDebounced() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(apply, 300);
    }

    watch(() => state.search, applyDebounced);
    watch(() => [state.type, state.client_id, state.is_active], apply);

    return { state, apply };
}
