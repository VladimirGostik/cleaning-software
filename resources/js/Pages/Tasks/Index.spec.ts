import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick, type App } from 'vue';

const stub = vi.hoisted(() => async (name: string) => {
    const { h } = await import('vue');

    return { name, render: () => h('div', { 'data-stub': name }) };
});

const flushPromises = (): Promise<void> => new Promise((resolve) => setTimeout(resolve));

const mocks = vi.hoisted(() => ({
    settle: vi.fn(),
    leave: vi.fn(),
    open: vi.fn(),
    close: vi.fn(),
    selectedId: { value: null as string | null },
    state: { value: 'closed' as string },
    createdId: { value: null as string | null },
}));

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn() },
    usePage: () => ({ url: '/tasks?task=t1' }),
}));

vi.mock('@/Layouts/Header.vue', async () => {
    const { h } = await import('vue');

    return {
        default: {
            setup:
                (_props: unknown, { slots }: { slots: { actions?: () => never } }) =>
                () =>
                    h('div', slots.actions?.()),
        },
    };
});
vi.mock('@/Composables/useTaskCreate', () => ({
    settleTaskCreate: () => mocks.settle(),
    useTaskCreate: () => ({
        state: mocks.state,
        sessionKey: { value: 1 },
        createdId: mocks.createdId,
        open: mocks.open,
        leave: mocks.leave,
    }),
}));
vi.mock('@/Composables/useTaskPanel', () => ({
    useTaskPanel: () => ({ selectedId: mocks.selectedId, close: mocks.close }),
}));
vi.mock('@/Composables/useAuthorization', () => ({ useAuthorization: () => ({ allows: () => true }) }));
vi.mock('@/Composables/usePageProps', () => ({ usePageProps: () => ({ value: { tenant: { active: null } } }) }));
vi.mock('@/Components/DataTable', async () => ({ TableFilters: await stub('TableFilters') }));
vi.mock('@/Components/EmptyState.vue', async () => ({ default: await stub('EmptyState') }));
vi.mock('@/Components/PageMainAction.vue', async () => {
    const { h } = await import('vue');

    return { default: { render: () => h('button', { 'data-stub': 'PageMainAction' }) } };
});
vi.mock('@/Components/ViewModeSwitch.vue', async () => ({ default: await stub('ViewModeSwitch') }));
vi.mock('@/Components/Tasks/TaskBoard.vue', async () => ({ default: await stub('TaskBoard') }));
vi.mock('@/Components/Tasks/TaskDetailBody.vue', async () => {
    const { h } = await import('vue');

    return {
        default: {
            props: ['task', 'creator'],
            render(this: { task: { id: string } | null; creator: unknown }) {
                return h('div', {
                    'data-stub': 'TaskDetailBody',
                    'data-task': this.task?.id ?? '',
                    'data-creator': this.creator ? 'yes' : 'no',
                });
            },
        },
    };
});
vi.mock('@/Components/Tasks/TaskTable.vue', async () => ({ default: await stub('TaskTable') }));
vi.mock('@/Components/Tasks/TaskDetailPanel.vue', async () => {
    const { h } = await import('vue');

    return {
        default: {
            props: ['heading'],
            emits: ['close'],
            render(this: { heading: string; $slots: { default?: () => unknown }; $emit: (e: string) => void }) {
                return h('div', { 'data-stub': 'TaskDetailPanel', 'data-heading': this.heading }, [
                    this.$slots.default?.() as never,
                    h('button', { 'data-close': '', onClick: () => this.$emit('close') }),
                ]);
            },
        },
    };
});
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
        context: { companies: [{ tenant_id: 't', name: 'T' }] },
        ...extra,
    });
    app.mount(host);
    await nextTick();

    return host;
}

beforeEach(() => {
    mocks.settle.mockReset().mockResolvedValue(true);
    mocks.leave.mockReset().mockResolvedValue(true);
    mocks.open.mockReset();
    mocks.close.mockReset().mockResolvedValue(undefined);
    mocks.selectedId.value = null;
    mocks.state.value = 'closed';
    mocks.createdId.value = null;
});

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

    it('does not open a draft when a pending create fails to settle', async () => {
        mocks.settle.mockResolvedValue(false);
        const host = await render({});

        host.querySelector<HTMLElement>('[data-stub="PageMainAction"]')!.click();
        await flushPromises();

        expect(mocks.open).not.toHaveBeenCalled();
    });

    it('closes the selected task before it opens a draft', async () => {
        mocks.selectedId.value = 'x';
        const host = await render({});

        host.querySelector<HTMLElement>('[data-stub="PageMainAction"]')!.click();
        await flushPromises();

        expect(mocks.close).toHaveBeenCalledTimes(1);
        expect(mocks.open).toHaveBeenCalledTimes(1);
        expect(mocks.close.mock.invocationCallOrder[0]).toBeLessThan(mocks.open.mock.invocationCallOrder[0]);
    });

    it('leaves the draft first and closes the task only when the leave succeeded', async () => {
        mocks.state.value = 'created';
        mocks.createdId.value = 't1';
        mocks.selectedId.value = 't1';
        const host = await render({ selected_task: { id: 't1' }, selected_attachments: {}, selected_thread: {} });

        host.querySelector<HTMLElement>('[data-close]')!.click();
        await flushPromises();

        expect(mocks.leave).toHaveBeenCalledTimes(1);
        expect(mocks.close).toHaveBeenCalledTimes(1);

        mocks.close.mockClear();
        mocks.leave.mockResolvedValue(false);
        host.querySelector<HTMLElement>('[data-close]')!.click();
        await flushPromises();

        expect(mocks.close).not.toHaveBeenCalled();
    });

    it('closes a plain task panel without touching the creator', async () => {
        mocks.selectedId.value = 't1';
        const host = await render({ selected_task: { id: 't1' }, selected_attachments: {}, selected_thread: {} });

        host.querySelector<HTMLElement>('[data-close]')!.click();
        await flushPromises();

        expect(mocks.leave).not.toHaveBeenCalled();
        expect(mocks.close).toHaveBeenCalledTimes(1);
    });

    it('shows the draft heading and no task in the body until the created task is the selected one', async () => {
        mocks.state.value = 'drafting';
        const props = { selected_task: { id: 't1' }, selected_attachments: {}, selected_thread: {} };
        const host = await render(props);

        const body = host.querySelector('[data-stub="TaskDetailBody"]')!;
        expect(host.querySelector('[data-stub="TaskDetailPanel"]')!.getAttribute('data-heading')).toBe('task_add');
        expect(body.getAttribute('data-task')).toBe('');
        expect(body.getAttribute('data-creator')).toBe('yes');

        app?.unmount();
        document.body.innerHTML = '';
        mocks.state.value = 'created';
        mocks.createdId.value = 't1';
        const created = await render(props);

        expect(created.querySelector('[data-stub="TaskDetailPanel"]')!.getAttribute('data-heading')).toBe(
            'task_detail_heading',
        );
        expect(created.querySelector('[data-stub="TaskDetailBody"]')!.getAttribute('data-task')).toBe('t1');
    });

    it('opens the panel for a draft without any selected task', async () => {
        mocks.state.value = 'drafting';
        const host = await render({ selected_task: null, selected_attachments: null, selected_thread: null });

        expect(host.querySelector('[data-stub="TaskDetailPanel"]')).not.toBeNull();
    });
});
