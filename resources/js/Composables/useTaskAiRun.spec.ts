import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, shallowRef, type EffectScope } from 'vue';
import { router } from '@inertiajs/vue3';
import { HttpError, getJson } from '@/utils/http';
import { useTaskAiRun } from './useTaskAiRun';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn(), reload: vi.fn() } }));
vi.mock('@/utils/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/http')>()),
    getJson: vi.fn(),
}));

const get = vi.mocked(getJson);
const reload = vi.mocked(router.reload);
const post = vi.mocked(router.post);

function run(overrides: Partial<App.Data.Tasks.TaskRunData> = {}): App.Data.Tasks.TaskRunData {
    return {
        id: 'r1',
        status: 'running',
        is_active: true,
        closed_task: false,
        started_by_name: 'Jana',
        tenant_id: 'c1',
        tenant_name: 'Firma',
        finished_at: null,
        actions: [],
        ...overrides,
    };
}

function thread(latest_run: App.Data.Tasks.TaskRunData | null): App.Data.Tasks.TaskThreadData {
    return {
        comments: [],
        latest_run,
        question: null,
        pending_actions: [],
        ai_available: true,
        can_comment: true,
        can_start_run: true,
        run_companies: [],
        max_body_chars: 2000,
    };
}

const current = shallowRef(thread(run()));
let scope: EffectScope;
let api: ReturnType<typeof useTaskAiRun>;

function mount(initial: App.Data.Tasks.TaskThreadData) {
    current.value = initial;
    scope = effectScope();
    api = scope.run(() => useTaskAiRun({ taskId: 't1', thread: () => current.value }))!;
}

function setHidden(hidden: boolean) {
    Object.defineProperty(document, 'hidden', { value: hidden, configurable: true });
    document.dispatchEvent(new Event('visibilitychange'));
}

async function advance(ms: number) {
    await vi.advanceTimersByTimeAsync(ms);
}

type ReloadOptions = { only: string[]; async: boolean; onFinish: () => void };
const lastReload = () => reload.mock.calls.at(-1)![0] as unknown as ReloadOptions;

beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    setHidden(false);
});

afterEach(() => {
    scope.stop();
    vi.useRealTimers();
});

function polled(overrides: Partial<App.Data.Tasks.TaskRunStatusData> = {}): App.Data.Tasks.TaskRunStatusData {
    return { latest_run_id: 'r1', status: 'running', is_active: true, ...overrides };
}

describe('polling', () => {
    it('polls the status endpoint every 3 s while the run is active and unchanged, without reloading', async () => {
        get.mockResolvedValue(polled());
        mount(thread(run()));

        await advance(2999);
        expect(get).not.toHaveBeenCalled();
        await advance(1);
        expect(get).toHaveBeenCalledTimes(1);
        expect(get).toHaveBeenCalledWith(
            '/tasks/t1/ai-run-status',
            expect.objectContaining({ signal: expect.anything() }),
        );

        await advance(3000);
        expect(get).toHaveBeenCalledTimes(2);
        expect(reload).not.toHaveBeenCalled();
    });

    it('never overlaps requests', async () => {
        let resolve!: (value: App.Data.Tasks.TaskRunStatusData) => void;
        get.mockImplementation(() => new Promise((r) => (resolve = r)));
        mount(thread(run()));

        await advance(10_000);
        expect(get).toHaveBeenCalledTimes(1);

        resolve(polled());
        await advance(3000);
        expect(get).toHaveBeenCalledTimes(2);
    });

    it.each([
        ['an inactive run', thread(run({ status: 'done', is_active: false }))],
        ['no run', thread(null)],
    ])('does not request anything for %s', async (_label, initial) => {
        mount(initial);

        await advance(20_000);

        expect(get).not.toHaveBeenCalled();
    });

    it('reloads thread and flash once when the status changed, then stops when inactive', async () => {
        get.mockResolvedValue(polled({ status: 'needs_input', is_active: false }));
        mount(thread(run()));

        await advance(3000);
        expect(reload).toHaveBeenCalledTimes(1);
        expect(lastReload().only).toEqual(['thread', 'flash']);
        expect(lastReload().async).toBe(true);

        current.value = thread(run({ status: 'needs_input', is_active: false }));
        lastReload().onFinish();
        await advance(12_000);

        expect(get).toHaveBeenCalledTimes(1);
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('reloads the task as well when the run finished', async () => {
        get.mockResolvedValue(polled({ status: 'done', is_active: false }));
        mount(thread(run()));

        await advance(3000);

        expect(lastReload().only).toEqual(['task', 'thread', 'flash']);
    });

    it('reloads when the latest run id differs', async () => {
        get.mockResolvedValue(polled({ latest_run_id: 'r2' }));
        mount(thread(run()));

        await advance(3000);

        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('reloads when the run is gone', async () => {
        get.mockResolvedValue(polled({ latest_run_id: null, status: null, is_active: false }));
        mount(thread(run()));

        await advance(3000);

        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('does not poll while a reload is pending and resumes when it finishes with the run still active', async () => {
        get.mockResolvedValueOnce(polled({ status: 'queued' })).mockResolvedValue(polled());
        mount(thread(run()));

        await advance(3000);
        await advance(9000);
        expect(get).toHaveBeenCalledTimes(1);

        lastReload().onFinish();
        await advance(3000);
        expect(get).toHaveBeenCalledTimes(2);
    });

    it('polls for a new run id that arrives with the thread', async () => {
        get.mockResolvedValue(polled({ latest_run_id: 'r2' }));
        mount(thread(run({ status: 'done', is_active: false })));

        current.value = thread(run({ id: 'r2' }));
        await nextTick();
        await advance(3000);

        expect(get).toHaveBeenCalledTimes(1);
        expect(reload).not.toHaveBeenCalled();
    });
});

describe('visibility', () => {
    it('does not tick while hidden and checks immediately when visible again', async () => {
        get.mockResolvedValue(polled());
        mount(thread(run()));

        setHidden(true);
        await advance(10_000);
        expect(get).not.toHaveBeenCalled();

        setHidden(false);
        await advance(0);
        expect(get).toHaveBeenCalledTimes(1);
    });
});

describe('failures', () => {
    it('stops and flags on 404', async () => {
        get.mockRejectedValue(new HttpError(404));
        mount(thread(run()));

        await advance(3000);
        await advance(12_000);

        expect(api.statusCheckFailed.value).toBe(true);
        expect(get).toHaveBeenCalledTimes(1);
    });

    it('flags on 500, keeps polling and clears the flag on success', async () => {
        get.mockRejectedValueOnce(new HttpError(500)).mockResolvedValue(polled());
        mount(thread(run()));

        await advance(3000);
        expect(api.statusCheckFailed.value).toBe(true);

        await advance(6000);
        expect(get).toHaveBeenCalledTimes(2);
        expect(api.statusCheckFailed.value).toBe(false);
    });
});

describe('backoff', () => {
    it('doubles the delay on consecutive status failures up to 60 s and resets after a success', async () => {
        get.mockRejectedValue(new HttpError(500));
        mount(thread(run()));

        const delays = [3000, 6000, 12_000, 24_000, 48_000, 60_000, 60_000];
        for (const [index, delay] of delays.entries()) {
            await advance(delay - 1);
            expect(get).toHaveBeenCalledTimes(index);
            await advance(1);
            expect(get).toHaveBeenCalledTimes(index + 1);
        }

        get.mockResolvedValue(polled());
        await advance(60_000);
        expect(get).toHaveBeenCalledTimes(delays.length + 1);

        await advance(2999);
        expect(get).toHaveBeenCalledTimes(delays.length + 1);
        await advance(1);
        expect(get).toHaveBeenCalledTimes(delays.length + 2);
    });

    it('backs off after a failed reload and resets after a successful one', async () => {
        get.mockResolvedValue(polled({ status: 'queued' }));
        mount(thread(run()));

        await advance(3000);
        expect((reload.mock.calls.at(-1)![0] as { onHttpException: () => boolean }).onHttpException()).toBe(false);
        lastReload().onFinish();

        await advance(5999);
        expect(get).toHaveBeenCalledTimes(1);
        await advance(1);
        expect(get).toHaveBeenCalledTimes(2);
    });
});

describe('dispose', () => {
    it('aborts the request and clears the timer', async () => {
        let signal!: AbortSignal;
        get.mockImplementation((_url, opts) => {
            signal = opts!.signal!;

            return new Promise(() => undefined);
        });
        mount(thread(run()));
        await advance(3000);

        scope.stop();

        expect(signal.aborted).toBe(true);
        await advance(10_000);
        expect(get).toHaveBeenCalledTimes(1);
    });

    it('clears a pending timer', async () => {
        mount(thread(run()));

        scope.stop();
        await advance(10_000);

        expect(get).not.toHaveBeenCalled();
    });
});

describe('start', () => {
    type PostOptions = Record<string, (...args: never[]) => unknown> & { only: string[] };
    const options = () => post.mock.calls[0][2] as unknown as PostOptions;

    it.each([null, 'c1'])('posts company_id %s as a partial visit', (companyId) => {
        mount(thread(null));

        api.start(companyId);

        expect(post.mock.calls[0][0]).toBe('/tasks/t1/ai-runs');
        expect(post.mock.calls[0][1]).toEqual({ company_id: companyId });
        expect(options().only).toEqual(['thread', 'flash']);
    });

    it('exposes validation errors and resets starting on finish', () => {
        mount(thread(null));
        api.start(null);

        options().onStart();
        expect(api.starting.value).toBe(true);

        (options().onError as (bag: Record<string, string>) => void)({ company_id: 'required', ai: 'no key' });
        expect(api.errors.value).toEqual({ company_id: 'required', ai: 'no key' });

        options().onFinish();
        expect(api.starting.value).toBe(false);
    });

    it('reports http failures under request and clears old errors on the next start', () => {
        mount(thread(null));
        api.start(null);

        expect(options().onHttpException()).toBe(false);
        expect(api.errors.value).toEqual({ request: 'task_ai_start_failed' });

        options().onStart();
        expect(api.errors.value).toEqual({});
    });
});
