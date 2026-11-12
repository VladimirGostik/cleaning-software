import { beforeEach, describe, expect, it, vi } from 'vitest';

const visit = vi.fn();
const page = { url: '/tasks?view=board&filter[status]=open' };

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: (...args: unknown[]) => visit(...args) },
    usePage: () => page,
}));

import { TASK_PANEL_PROPS, useTaskPanel } from './useTaskPanel';

beforeEach(() => {
    visit.mockClear();
    page.url = '/tasks?view=board&filter[status]=open';
});

describe('useTaskPanel', () => {
    it('opens a task by adding task to the current query as a new history entry', () => {
        useTaskPanel().open('abc');

        expect(visit).toHaveBeenCalledWith('/tasks?view=board&filter%5Bstatus%5D=open&task=abc', {
            only: TASK_PANEL_PROPS,
            preserveState: true,
            preserveScroll: true,
            replace: false,
        });
    });

    it('closes by removing task and keeping the rest', () => {
        page.url = '/tasks?view=board&task=abc';

        useTaskPanel().close();

        expect(visit.mock.calls[0][0]).toBe('/tasks?view=board');
        expect(visit.mock.calls[0][1]).toMatchObject({ only: TASK_PANEL_PROPS, preserveState: true });
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
