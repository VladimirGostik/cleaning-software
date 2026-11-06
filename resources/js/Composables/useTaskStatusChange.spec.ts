import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { useTaskStatusChange } from './useTaskStatusChange';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn() } }));

const post = vi.mocked(router.post);

type Options = {
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
};

function lastOptions(): Options {
    return post.mock.calls.at(-1)?.[2] as Options;
}

const task = { id: 't1', title: 'Umyť okná', status: 'open' as const };

describe('useTaskStatusChange', () => {
    let toasts: string[];

    beforeEach(() => {
        post.mockReset();
        toasts = [];
        window.addEventListener('app-toast', (event) => toasts.push((event as CustomEvent).detail.message), {
            once: false,
        });
    });

    it('sets the overlay before the request and clears it on finish', () => {
        const { pending, change } = useTaskStatusChange();

        change(task, 'waiting');

        expect(post).toHaveBeenCalledWith('/tasks/t1/status', { status: 'waiting' }, expect.any(Object));
        expect(pending.value).toEqual({ t1: 'waiting' });

        lastOptions().onFinish?.();

        expect(pending.value).toEqual({});
    });

    it('announces a successful move', () => {
        const { announcement, change } = useTaskStatusChange();

        change(task, 'done');
        lastOptions().onSuccess?.();

        expect(announcement.value).toBe('task_moved_announce');
    });

    it('toasts the status error and clears the overlay on failure', () => {
        const { pending, change } = useTaskStatusChange();

        change(task, 'done');
        lastOptions().onError?.({ status: 'Chyba stavu' });
        lastOptions().onFinish?.();

        expect(toasts).toContain('Chyba stavu');
        expect(pending.value).toEqual({});
    });

    it('ignores a second change while the first is in flight', () => {
        const { change } = useTaskStatusChange();

        change(task, 'waiting');
        change(task, 'done');

        expect(post).toHaveBeenCalledTimes(1);
    });

    it('does nothing for the current status', () => {
        const { pending, change } = useTaskStatusChange();

        change(task, 'open');

        expect(post).not.toHaveBeenCalled();
        expect(pending.value).toEqual({});
    });
});
