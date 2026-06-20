import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export interface ContractTemplateFilterState {
    search: string;
    category: App.Enums.ContractCategoryEnum | undefined;
}

export function useContractTemplateFilters(
    initial: App.Data.ContractTemplates.ContractTemplateIndexFilterData,
): { state: ContractTemplateFilterState; apply(): void } {
    const state = reactive<ContractTemplateFilterState>({
        search: initial.search ?? '',
        category: (initial.category as App.Enums.ContractCategoryEnum | null) ?? undefined,
    });

    let timer: ReturnType<typeof setTimeout> | null = null;

    function apply(): void {
        router.get(
            '/contract-templates',
            {
                search: state.search || undefined,
                category: state.category,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['templates', 'filters'],
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
    watch(() => state.category, apply);

    return { state, apply };
}
