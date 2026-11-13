import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope } from 'vue';
import { router } from '@inertiajs/vue3';

import { SAVE_DEBOUNCE_MS, flushActiveTaskEdit, runAfterTaskEdits, useTaskInlineEdit } from './useTaskInlineEdit';

const toastError = vi.fn();

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@/Composables/useToast', () => ({ useToast: () => ({ error: toastError }) }));
vi.mock('@inertiajs/vue3', () => ({ router: { patch: vi.fn() } }));

type Options = {
    only: string[];
    async: boolean;
    onStart: () => void;
    onSuccess: () => void;
    onNetworkError: () => void;
    onCancel: () => void;
    onError: (errors: Record<string, string>) => void;
    onHttpException: (response: { status: number }) => boolean | void;
    onFinish: () => void;
};

const patch = vi.mocked(router.patch);

function call(index: number): { url: string; data: Record<string, unknown>; options: Options } {
    const [url, data, options] = patch.mock.calls[index] as unknown as [string, Record<string, unknown>, Options];

    return { url, data, options };
}

function succeed(index: number): void {
    call(index).options.onSuccess();
    call(index).options.onFinish();
}

function setup() {
    const scope = effectScope();
    const api = scope.run(() => useTaskInlineEdit({ taskId: 't1' }))!;

    return { api, scope };
}

describe('useTaskInlineEdit', () => {
    beforeEach(() => {
        patch.mockReset();
        patch.mockImplementation(((_url: string, _data: unknown, options: Options) => {
            options.onStart();
        }) as never);
        toastError.mockReset();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('patches only the given keys and refreshes the list props', () => {
        const { api } = setup();

        api.save({ priority: 'high' });

        const { url, data, options } = call(0);
        expect(url).toBe('/tasks/t1');
        expect(data).toEqual({ priority: 'high' });
        expect(options.only).toEqual(['tasks', 'board', 'selected_task', 'flash']);
        expect(options.async).toBe(true);
        expect(api.state.value).toBe('saving');
    });

    it('keeps one request in flight and merges later saves in order', () => {
        const { api } = setup();

        api.save({ priority: 'high' });
        api.save({ title: 'B' });
        api.save({ title: 'C' });

        expect(patch).toHaveBeenCalledTimes(1);

        succeed(0);

        expect(patch).toHaveBeenCalledTimes(2);
        expect(call(1).data).toEqual({ title: 'C' });

        succeed(1);

        expect(api.state.value).toBe('saved');
    });

    it('shows a 422 under the field, keeps state error, and clears it on a later success', () => {
        const { api } = setup();

        api.save({ title: '' });
        call(0).options.onError({ title: 'Povinné' });
        call(0).options.onFinish();

        expect(api.errors.value).toEqual({ title: 'Povinné' });
        expect(api.state.value).toBe('error');

        api.save({ title: 'Okná' });
        succeed(1);

        expect(api.errors.value).toEqual({});
        expect(api.state.value).toBe('saved');
    });

    it('toasts forbidden, drops queued saves and bumps the reset counter on 403', () => {
        const { api } = setup();

        api.save({ title: 'A' });
        api.save({ priority: 'low' });

        expect(call(0).options.onHttpException({ status: 403 })).toBe(false);
        call(0).options.onFinish();

        expect(toastError).toHaveBeenCalledWith('forbidden');
        expect(api.resets.value).toBe(1);
        expect(api.state.value).toBe('error');
        expect(patch).toHaveBeenCalledTimes(1);
    });

    it('sends a debounced save once after the delay', () => {
        const { api } = setup();

        api.saveDebounced({ description: 'a' });
        api.saveDebounced({ description: 'ab' });
        vi.advanceTimersByTime(SAVE_DEBOUNCE_MS - 1);

        expect(patch).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);

        expect(patch).toHaveBeenCalledTimes(1);
        expect(call(0).data).toEqual({ description: 'ab' });
    });

    it('a blur save right after typing sends one request and cancels the timer', () => {
        const { api } = setup();

        api.saveDebounced({ description: 'ab' });
        api.save({ description: 'ab' });
        succeed(0);
        vi.advanceTimersByTime(SAVE_DEBOUNCE_MS * 2);

        expect(patch).toHaveBeenCalledTimes(1);
    });

    it('flush sends a pending debounced save and resolves when the queue is empty', async () => {
        const { api } = setup();

        api.saveDebounced({ description: 'ab' });
        const flushed = vi.fn();
        const promise = api.flush().then(flushed);

        expect(patch).toHaveBeenCalledTimes(1);

        await Promise.resolve();
        expect(flushed).not.toHaveBeenCalled();

        succeed(0);
        await promise;

        expect(flushed).toHaveBeenCalled();
    });

    it('flush resolves at once when nothing is pending', async () => {
        const { api } = setup();

        await expect(api.flush()).resolves.toBeUndefined();
        expect(patch).not.toHaveBeenCalled();
    });

    it('registers the active instance for panel navigation and sends pending saves on dispose', async () => {
        const { api, scope } = setup();

        api.saveDebounced({ description: 'x' });
        const promise = flushActiveTaskEdit();

        expect(patch).toHaveBeenCalledTimes(1);
        succeed(0);
        await promise;

        api.saveDebounced({ description: 'y' });
        scope.stop();

        expect(patch).toHaveBeenCalledTimes(2);
        await expect(flushActiveTaskEdit()).resolves.toBeUndefined();
    });

    it('runs the action at once when idle and only after the pending save when not', async () => {
        const action = vi.fn();
        await runAfterTaskEdits(action);
        expect(action).toHaveBeenCalledTimes(1);

        const { api, scope } = setup();
        api.saveDebounced({ description: 'x' });
        const promise = runAfterTaskEdits(action);

        expect(action).toHaveBeenCalledTimes(1);
        succeed(0);
        await promise;
        expect(action).toHaveBeenCalledTimes(2);
        scope.stop();
    });

    it.each([
        ['a 5xx response', (o: Options) => o.onHttpException({ status: 500 })],
        ['a network error', (o: Options) => o.onNetworkError()],
        ['a cancelled visit', (o: Options) => o.onCancel()],
    ])('ends in error and re-queues the keys after %s', (_name, fail) => {
        const { api } = setup();

        api.save({ title: 'A' });
        fail(call(0).options);
        call(0).options.onFinish();

        expect(api.state.value).toBe('error');
        expect(patch).toHaveBeenCalledTimes(1);

        api.save({ priority: 'high' });

        expect(call(1).data).toEqual({ title: 'A', priority: 'high' });
    });

    it('keeps a newer value queued for the same key when the older one is lost', () => {
        const { api } = setup();

        api.save({ title: 'A' });
        api.save({ title: 'B' });
        call(0).options.onNetworkError();
        call(0).options.onFinish();

        expect(patch).toHaveBeenCalledTimes(2);
        expect(call(1).data).toEqual({ title: 'B' });
    });

    it('flush retries a lost save once and resolves even if it is lost again', async () => {
        const { api } = setup();

        api.save({ title: 'A' });
        call(0).options.onCancel();
        call(0).options.onFinish();

        const flushed = api.flush();

        expect(call(1).data).toEqual({ title: 'A' });
        call(1).options.onNetworkError();
        call(1).options.onFinish();

        await expect(flushed).resolves.toBeUndefined();
        expect(api.state.value).toBe('error');
    });

    it('treats a visit that never starts as a lost save and does not hang flush', async () => {
        patch.mockImplementation(() => undefined);
        const { api } = setup();

        api.save({ title: 'A' });

        expect(api.state.value).toBe('error');
        await expect(api.flush()).resolves.toBeUndefined();
        expect(patch).toHaveBeenCalledTimes(2);
    });

    it('is saved only after onSuccess', () => {
        const { api } = setup();

        api.save({ title: 'A' });
        call(0).options.onFinish();

        expect(api.state.value).toBe('error');
    });

    it('keeps a save queued while the id getter returns null and sends it to the id that arrives', async () => {
        let id: string | null = null;
        const scope = effectScope();
        const api = scope.run(() => useTaskInlineEdit({ taskId: () => id }))!;

        api.save({ title: 'Zav' });

        expect(patch).not.toHaveBeenCalled();
        expect(api.state.value).toBe('idle');

        id = 'new1';
        const flushed = api.flush();

        expect(patch).toHaveBeenCalledTimes(1);
        expect(call(0).url).toBe('/tasks/new1');
        expect(call(0).data).toEqual({ title: 'Zav' });

        succeed(0);
        await flushed;
    });
});
