import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export interface EmployeeFilterState {
    search: string;
    role: string | undefined;
    is_active: boolean | undefined;
}

export function useEmployeeFilters(initial: {
    search?: string | null;
    role?: string | null;
    is_active?: boolean | null;
}): { state: EmployeeFilterState; apply(): void } {
    const state = reactive<EmployeeFilterState>({
        search: initial.search ?? '',
        role: initial.role ?? undefined,
        is_active: initial.is_active ?? undefined,
    });

    let timer: ReturnType<typeof setTimeout> | null = null;

    function apply(): void {
        router.get(
            '/employees',
            {
                search: state.search || undefined,
                role: state.role,
                is_active: state.is_active,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['employees', 'filters'],
            },
        );
    }

    function applyDebounced(): void {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(apply, 300);
    }

    watch(() => state.search, applyDebounced);
    watch(() => [state.role, state.is_active], apply);

    return { state, apply };
}
