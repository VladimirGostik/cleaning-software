import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick, type App } from 'vue';

const stub = vi.hoisted(() => async (name: string) => {
    const { h } = await import('vue');

    return { name, render: () => h('div', { 'data-stub': name }) };
});

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn() },
    usePage: () => ({ url: '/tasks?task=t1' }),
}));

vi.mock('@/Layouts/Header.vue', async () => ({ default: await stub('Header') }));
vi.mock('@/Components/DataTable', async () => ({ TableFilters: await stub('TableFilters') }));
vi.mock('@/Components/EmptyState.vue', async () => ({ default: await stub('EmptyState') }));
vi.mock('@/Components/PageMainAction.vue', async () => ({ default: await stub('PageMainAction') }));
vi.mock('@/Components/ViewModeSwitch.vue', async () => ({ default: await stub('ViewModeSwitch') }));
vi.mock('@/Components/Tasks/TaskBoard.vue', async () => ({ default: await stub('TaskBoard') }));
vi.mock('@/Components/Tasks/TaskFormDrawer.vue', async () => ({ default: await stub('TaskFormDrawer') }));
vi.mock('@/Components/Tasks/TaskTable.vue', async () => ({ default: await stub('TaskTable') }));
vi.mock('@/Components/Tasks/TaskDetailPanel.vue', async () => ({ default: await stub('TaskDetailPanel') }));
vi.mock('@/Composables/useTaskFilters', () => ({
    useTaskFilters: () => ({
        query: { value: { filters: {} } },
        definitions: { value: [] },
        reloadOnly: [],
        hasActiveFilters: { value: false },
        chipSignature: { value: '' },
        changeFilter: vi.fn(),
        clearFilters: vi.fn(),
        changeView: vi.fn(),
    }),
}));
vi.mock('@/Composables/useTaskStatusChange', async () => {
    const { ref } = await import('vue');
    return { useTaskStatusChange: () => ({ pending: ref({}), announcement: ref(''), change: vi.fn() }) };
});
vi.mock('@/Composables/useTaskTransition', async () => {
    const { ref } = await import('vue');
    return { useTaskTransition: () => ({ processingId: ref(null), transition: vi.fn() }) };
});

import Index from './Index.vue';

let app: App | null = null;

async function render(extra: Record<string, unknown>): Promise<HTMLElement> {
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp(Index, {
        view: 'table',
        tasks: { data: [], total: 1 },
        board: null,
        context: { companies: [] },
        ...extra,
    });
    app.mount(host);
    await nextTick();

    return host;
}

afterEach(() => {
    app?.unmount();
    app = null;
    document.body.innerHTML = '';
});

describe('Tasks Index', () => {
    it('renders the detail panel only with a selected task', async () => {
        const host = await render({
            selected_task: { id: 't1' },
            selected_attachments: {},
            selected_thread: {},
        });

        expect(host.querySelector('[data-stub="TaskDetailPanel"]')).not.toBeNull();
    });

    it('renders no panel when selected_task is null', async () => {
        const host = await render({ selected_task: null, selected_attachments: null, selected_thread: null });

        expect(host.querySelector('[data-stub="TaskDetailPanel"]')).toBeNull();
    });
});
