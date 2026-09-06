import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export interface UseFiltersOptions<T extends Record<string, unknown>> {
    url: string;
    initialFilters: T;
    defaults?: Partial<T>;
    debounceMs?: number;
    searchKey?: keyof T;
    immediateKeys?: (keyof T)[];
    transform?: (filters: T) => Record<string, unknown>;
}

export function useFilters<T extends Record<string, unknown>>(options: UseFiltersOptions<T>) {
    const { url, initialFilters, defaults, debounceMs = 400, searchKey, immediateKeys = [], transform } = options;

    const filters = reactive<T>({ ...initialFilters } as T);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    function buildPayload(): Record<string, unknown> {
        const raw = { ...filters } as Record<string, unknown>;
        if (transform) {
            return transform(filters as T);
        }
        return raw;
    }

    function navigate() {
        router.get(url, buildPayload() as Record<string, string>, {
            preserveState: true,
            replace: true,
        });
    }

    function navigateDebounced() {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(() => {
            navigate();
            debounceTimer = null;
        }, debounceMs);
    }

    // Watch search key with debounce
    if (searchKey) {
        watch(
            () => (filters as Record<string, unknown>)[searchKey as string],
            () => {
                navigateDebounced();
            },
        );
    }

    // Watch immediate keys
    const allImmediateKeys = [...immediateKeys];
    if (allImmediateKeys.length > 0) {
        watch(
            allImmediateKeys.map((k) => () => (filters as Record<string, unknown>)[k as string]),
            () => {
                navigate();
            },
        );
    }

    function setSort(sort: string) {
        (filters as Record<string, unknown>)['sort'] = sort;
        navigate();
    }

    function setPerPage(perPage: number) {
        (filters as Record<string, unknown>)['perPage'] = perPage;
        navigate();
    }

    function clearFilters() {
        const cleared = { ...initialFilters, ...(defaults ?? {}) } as Record<string, unknown>;
        for (const key of Object.keys(cleared)) {
            (filters as Record<string, unknown>)[key] = cleared[key];
        }
        navigate();
    }

    return {
        filters,
        setSort,
        setPerPage,
        clearFilters,
        navigate,
    };
}
