import { beforeEach, describe, expect, it, vi } from 'vitest';

const visit = vi.fn();
const flushActive = vi.fn(() => Promise.resolve());
const settle = vi.fn(() => Promise.resolve(true));
const page = { url: '/tasks?view=board&filter[status]=open' };

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: (...args: unknown[]) => visit(...args) },
    usePage: () => page,
}));

vi.mock('@/Composables/useTaskCreate', () => ({ settleTaskCreate: () => settle() }));
vi.mock('@/Composables/useTaskInlineEdit', () => ({ flushActiveTaskEdit: () => flushActive() }));

import { TASK_PANEL_PROPS, useTaskPanel } from './useTaskPanel';

beforeEach(() => {
    visit.mockClear();
    flushActive.mockClear();
    settle.mockClear();
    settle.mockImplementation(() => Promise.resolve(true));
    flushActive.mockImplementation(() => Promise.resolve());
    page.url = '/tasks?view=board&filter[status]=open';
});

describe('useTaskPanel', () => {
    it('opens a task by adding task to the current query as a new history entry', async () => {
        await useTaskPanel().open('abc');

        expect(visit).toHaveBeenCalledWith('/tasks?view=board&filter%5Bstatus%5D=open&task=abc', {
            only: TASK_PANEL_PROPS,
            preserveState: true,
            preserveScroll: true,
            replace: false,
        });
    });

    it('closes by removing task and keeping the rest', async () => {
        page.url = '/tasks?view=board&task=abc';

        await useTaskPanel().close();

        expect(visit.mock.calls[0][0]).toBe('/tasks?view=board');
        expect(visit.mock.calls[0][1]).toMatchObject({ only: TASK_PANEL_PROPS, preserveState: true });
    });

    it('sends the pending inline save before navigating', async () => {
        let release: () => void = () => {};
        flushActive.mockImplementation(() => new Promise<void>((resolve) => (release = resolve)));

        const closing = useTaskPanel().close();
        await new Promise((resolve) => setTimeout(resolve));

        expect(flushActive).toHaveBeenCalledTimes(1);
        expect(visit).not.toHaveBeenCalled();

        release();
        await closing;

        expect(visit).toHaveBeenCalledTimes(1);
    });

    it('waits for a pending create before opening another task', async () => {
        let release: (ok: boolean) => void = () => {};
        settle.mockImplementation(() => new Promise<boolean>((resolve) => (release = resolve)));

        const opening = useTaskPanel().open('abc');

        expect(visit).not.toHaveBeenCalled();
        expect(flushActive).not.toHaveBeenCalled();

        release(true);
        await opening;

        expect(visit).toHaveBeenCalledTimes(1);
    });

    it('does not navigate when the create failed', async () => {
        settle.mockImplementation(() => Promise.resolve(false));

        await useTaskPanel().open('abc');

        expect(visit).not.toHaveBeenCalled();
        expect(flushActive).not.toHaveBeenCalled();
    });

    it('swaps the task id', () => {
        page.url = '/tasks?task=abc';

        expect(useTaskPanel().href('def')).toBe('/tasks?task=def');
    });

    it('reads the selected id from the page url', () => {
        expect(useTaskPanel().selectedId.value).toBeNull();

        page.url = '/tasks?task=xyz';

        expect(useTaskPanel().selectedId.value).toBe('xyz');
    });
});
