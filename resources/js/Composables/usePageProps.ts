import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

/**
 * Read `.value` inside computed/template; never destructure at setup time — the returned
 * ComputedRef stays fresh across partial reloads and tenant switches.
 */
export function usePageProps(): ComputedRef<SharedProps> {
    const page = usePage();
    return computed(() => page.props as SharedProps);
}
