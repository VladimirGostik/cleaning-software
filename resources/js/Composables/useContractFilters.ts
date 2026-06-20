import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export interface ContractFilterState {
    search: string;
    status: App.Enums.ContractStatusEnum | undefined;
    category: App.Enums.ContractCategoryEnum | undefined;
    term_type: App.Enums.ContractTermTypeEnum | undefined;
}

export function useContractFilters(initial: App.Data.Contracts.ContractIndexFilterData): {
    state: ContractFilterState;
    apply(): void;
} {
    const state = reactive<ContractFilterState>({
        search: initial.search ?? '',
        status: (initial.status as App.Enums.ContractStatusEnum | null) ?? undefined,
        category: (initial.category as App.Enums.ContractCategoryEnum | null) ?? undefined,
        term_type: (initial.term_type as App.Enums.ContractTermTypeEnum | null) ?? undefined,
    });

    let timer: ReturnType<typeof setTimeout> | null = null;

    function apply(): void {
        router.get(
            '/contracts',
            {
                search: state.search || undefined,
                status: state.status,
                category: state.category,
                term_type: state.term_type,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['contracts', 'filters'],
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
    watch(() => [state.status, state.category, state.term_type], apply);

    return { state, apply };
}
