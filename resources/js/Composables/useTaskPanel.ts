import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

export const TASK_PANEL_PROPS = ['selected_task', 'selected_attachments', 'selected_thread'];

export function useTaskPanel() {
    const page = usePage();

    const selectedId = computed(() => new URL(page.url, window.location.origin).searchParams.get('task'));

    function href(id: string | null): string {
        const url = new URL(page.url, window.location.origin);

        if (id === null) {
            url.searchParams.delete('task');
        } else {
            url.searchParams.set('task', id);
        }

        return `${url.pathname}${url.search}`;
    }

    function visit(id: string | null): void {
        router.visit(href(id), {
            only: TASK_PANEL_PROPS,
            preserveState: true,
            preserveScroll: true,
            replace: false,
        });
    }

    return {
        selectedId,
        href,
        open: (id: string) => visit(id),
        close: () => visit(null),
    };
}
