import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, ref } from 'vue';
import type { EffectScope } from 'vue';

const reload = vi.fn();

vi.mock('@inertiajs/vue3', () => ({ router: { reload: (...args: unknown[]) => reload(...args) } }));

import { clearTaskActivityCache, useTaskActivity } from './useTaskActivity';

type Feed = App.Data.Tasks.TaskActivityFeedData;

const feedA: Feed = {
    entries: [
        {
            id: 'e1',
            kind: 'updated',
            actor_name: 'Jana',
            occurred_at: '2026-11-10T08:00:00+00:00',
            changes: [],
            attachment_name: null,
        },
    ],
    has_older: false,
};

let scope: EffectScope | null = null;
let taskCounter = 0;

function setup(taskId = `task-${++taskCounter}`, activityInitial: Feed | null | undefined = undefined) {
    const activity = ref<Feed | null | undefined>(activityInitial);
    const task = ref({
        created_by_name: 'Admin',
        created_at: '2026-11-01T08:00:00+00:00',
    } as App.Data.Tasks.TaskDetailData);
    const thread = ref({ comments: [] } as unknown as App.Data.Tasks.TaskThreadData);
    const attachments = ref({ attachments: [] } as unknown as App.Data.Tasks.TaskAttachmentListData);
    scope = effectScope();
    const api = scope.run(() =>
        useTaskActivity({
            taskId,
            activity: () => activity.value,
            task: () => task.value,
            thread: () => thread.value,
            attachments: () => attachments.value,
        }),
    )!;

    return { api, activity, task, thread, attachments };
}

function lastOptions(): Record<string, (() => void) | unknown> {
    return reload.mock.calls.at(-1)![0] as Record<string, unknown>;
}

beforeEach(() => {
    vi.useFakeTimers();
    reload.mockReset();
    window.localStorage.clear();
});

afterEach(() => {
    scope?.stop();
    scope = null;
    vi.useRealTimers();
});

describe('useTaskActivity', () => {
    it('does not load while the comments tab is shown', async () => {
        const { task } = setup();
        task.value = { ...task.value };
        await nextTick();
        vi.advanceTimersByTime(1000);

        expect(reload).not.toHaveBeenCalled();
    });

    it('loads once on the first open of all and not on switching back and forth', () => {
        const { api } = setup();

        api.setView('all');
        expect(reload).toHaveBeenCalledTimes(1);
        expect(lastOptions()).toMatchObject({
            only: ['selected_activity'],
            preserveState: true,
            preserveScroll: true,
            async: true,
        });

        api.setView('comments');
        api.setView('all');
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('reloads once, debounced, when task, thread or attachments change while all is open', async () => {
        const { api, task, thread, attachments } = setup();
        api.setView('all');
        reload.mockClear();

        task.value = { ...task.value };
        thread.value = { ...thread.value };
        attachments.value = { ...attachments.value };
        await nextTick();
        expect(reload).not.toHaveBeenCalled();

        vi.advanceTimersByTime(500);
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('refreshes in the background when returning to all after a change on the comments tab', async () => {
        const { api, task } = setup();
        api.setView('all');
        api.setView('comments');
        reload.mockClear();

        task.value = { ...task.value };
        await nextTick();
        vi.advanceTimersByTime(1000);
        expect(reload).not.toHaveBeenCalled();

        api.setView('all');
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('keeps the last feed when activity becomes undefined and clears it on null', async () => {
        const { api, activity } = setup();
        api.setView('all');

        activity.value = feedA;
        await nextTick();
        expect(api.feed.value.map((item) => item.type)).toEqual(['entry']);

        activity.value = undefined;
        await nextTick();
        expect(api.feed.value).toHaveLength(1);

        activity.value = null;
        await nextTick();
        expect(api.feed.value).toHaveLength(0);
    });

    it('flags a first-load failure and retries', async () => {
        const { api } = setup();
        api.setView('all');
        expect(api.loading.value).toBe(true);

        (lastOptions().onHttpException as () => void)();
        (lastOptions().onFinish as () => void)();
        await nextTick();
        expect(api.failed.value).toBe(true);
        expect(api.loading.value).toBe(false);

        api.retry();
        expect(reload).toHaveBeenCalledTimes(2);
        expect(api.failed.value).toBe(false);
    });

    it('ignores a background failure', async () => {
        const { api, task } = setup();
        api.setView('all');
        (lastOptions().onFinish as () => void)();
        task.value = { ...task.value };
        await nextTick();
        vi.advanceTimersByTime(500);

        (lastOptions().onNetworkError as () => void)();
        expect(api.failed.value).toBe(false);
    });

    it('remembers the sort', () => {
        const first = setup();
        expect(first.api.sort.value).toBe('oldest');
        first.api.toggleSort();
        expect(window.localStorage.getItem('kg.taskFeedSort')).toBe('newest');

        scope?.stop();
        expect(setup().api.sort.value).toBe('newest');
    });

    it('keeps the view across a remount of the same task and shows the cached entries', async () => {
        const first = setup('same');
        first.api.setView('all');
        first.activity.value = feedA;
        await nextTick();
        scope?.stop();
        reload.mockClear();

        const second = setup('same', feedA);

        expect(second.api.view.value).toBe('all');
        expect(second.api.feed.value.map((item) => item.type)).toEqual(['entry']);
        expect(second.api.loading.value).toBe(false);
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it("does not show another task's entries after a remount without a cache and loads in the foreground", () => {
        window.sessionStorage.setItem('kg.taskFeedView.fresh', 'all');

        const { api } = setup('fresh', feedA);

        expect(api.view.value).toBe('all');
        expect(api.feed.value).toHaveLength(0);
        expect(api.loading.value).toBe(true);
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('adopts the feed that reaches the prop one tick after the success callback', async () => {
        const { api, activity } = setup('late', null);
        api.setView('all');
        expect(api.loading.value).toBe(true);
        expect(api.feed.value).toHaveLength(0);

        (lastOptions().onSuccess as () => void)();
        (lastOptions().onFinish as () => void)();
        await nextTick();
        expect(api.loading.value).toBe(false);

        activity.value = feedA;
        await nextTick();

        expect(api.feed.value.map((item) => item.type)).toEqual(['entry']);
    });

    it('adopts the prop on success when the reload brought an identical feed that never changed it', async () => {
        const { api } = setup('same-data', feedA);
        api.setView('all');
        expect(api.feed.value).toHaveLength(0);

        (lastOptions().onSuccess as () => void)();
        (lastOptions().onFinish as () => void)();
        await nextTick();
        await nextTick();

        expect(api.feed.value.map((item) => item.type)).toEqual(['entry']);
        expect(api.loading.value).toBe(false);
    });

    it('ignores a stale prop until a load was requested', async () => {
        const { api, activity } = setup('idle');
        activity.value = feedA;
        await nextTick();

        api.setView('all');

        expect(api.feed.value).toHaveLength(0);
    });

    it('shows the loading row and an empty feed with an undefined initial prop, then adopts the arriving feed', async () => {
        const { api, activity } = setup('undef', undefined);
        api.setView('all');

        expect(api.loading.value).toBe(true);
        expect(api.feed.value).toHaveLength(0);

        (lastOptions().onSuccess as () => void)();
        (lastOptions().onFinish as () => void)();
        await nextTick();
        await nextTick();
        expect(api.loading.value).toBe(false);
        expect(api.failed.value).toBe(false);
        expect(api.feed.value).toHaveLength(0);

        activity.value = feedA;
        await nextTick();
        expect(api.feed.value).toHaveLength(1);
    });

    it('loads in the foreground on a restored all view with an undefined prop and no cache', () => {
        window.sessionStorage.setItem('kg.taskFeedView.restored', 'all');

        const { api } = setup('restored', undefined);

        expect(api.loading.value).toBe(true);
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('never puts a late answer for a disposed task on the next one', async () => {
        const a = setup('task-a', undefined);
        a.api.setView('all');
        const lateOptions = lastOptions();
        scope?.stop();

        a.activity.value = feedA;
        (lateOptions.onSuccess as () => void)();
        await nextTick();
        await nextTick();

        scope?.stop();
        window.sessionStorage.setItem('kg.taskFeedView.task-b', 'all');
        const b = setup('task-b', feedA);
        expect(b.api.feed.value).toHaveLength(0);

        scope?.stop();
        expect(setup('task-a', undefined).api.feed.value).toHaveLength(0);
    });

    it('caps the cache at 20 tasks and clears it on demand', async () => {
        for (let i = 0; i < 22; i++) {
            const { api, activity } = setup(`cap-${i}`);
            api.setView('all');
            activity.value = feedA;
            await nextTick();
            scope?.stop();
        }

        expect(setup('cap-0', undefined).api.feed.value).toHaveLength(0);
        scope?.stop();
        expect(setup('cap-1', undefined).api.feed.value).toHaveLength(0);
        scope?.stop();
        expect(setup('cap-21', undefined).api.feed.value).toHaveLength(1);
        scope?.stop();

        clearTaskActivityCache();
        expect(setup('cap-21', undefined).api.feed.value).toHaveLength(0);
    });
});
