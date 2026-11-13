import { computed, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { settleTaskCreate } from '@/Composables/useTaskCreate';
import { flushActiveTaskEdit } from '@/Composables/useTaskInlineEdit';

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

    async function visit(id: string | null): Promise<void> {
        if (!(await settleTaskCreate())) return;

        await nextTick();

        const target = href(id);

        await flushActiveTaskEdit();

        router.visit(target, {
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
