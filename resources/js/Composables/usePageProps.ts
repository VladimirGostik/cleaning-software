import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

export function usePageProps(): SharedProps {
    // Cast through unknown to satisfy strict checking — Inertia's runtime guarantees the shape.
    return usePage().props as unknown as SharedProps;
}
