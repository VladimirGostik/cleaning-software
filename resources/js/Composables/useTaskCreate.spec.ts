import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, ref, type EffectScope } from 'vue';
import { router } from '@inertiajs/vue3';

import {
    CREATE_MIN_CHARS,
    CREATE_PAUSE_MS,
    TASK_CREATE_PROPS,
    settleTaskCreate,
    useTaskCreate,
    type TaskCreateSession,
} from './useTaskCreate';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn(), replace: vi.fn() } }));

type Options = {
    async: boolean;
    only: string[];
    replace: boolean;
    onStart: () => void;
    onSuccess: (page: { url: string; props: Record<string, unknown> }) => void;
    onError: (errors: Record<string, string>) => void;
    onHttpException: (response: { status: number }) => boolean | void;
    onNetworkError: () => void;
    onFinish: () => void;
};

const post = vi.mocked(router.post);
const replace = vi.mocked(router.replace);

function call(index: number): { url: string; body: Record<string, unknown>; options: Options } {
    const [url, body, options] = post.mock.calls[index] as unknown as [string, Record<string, unknown>, Options];

    return { url, body, options };
}

function arrive(index: number, id = 'n1', props: Record<string, unknown> = { selected_task: { id } }): void {
    call(index).options.onSuccess({ url: `/tasks?view=board&task=${id}`, props });
    call(index).options.onFinish();
}

function fail(index: number, run: (options: Options) => void): void {
    run(call(index).options);
    call(index).options.onFinish();
}

const context = {
    companies: [{ tenant_id: 'tA', name: 'Alfa' }],
    members: {},
    reminder_slots: [],
    default_remind_on: '2026-11-14',
    default_remind_slot: 'morning',
} as unknown as App.Data.Tasks.TaskFormContextData;

let selected = ref<string | null>(null);
let mayEdit = true;
const scopes: EffectScope[] = [];

function setup(activeId: string | null = 'tA'): TaskCreateSession {
    const scope = effectScope();
    scopes.push(scope);

    return scope.run(() =>
        useTaskCreate({
            context: () => context,
            activeTenantId: () => activeId,
            selectedTaskId: () => selected.value,
            mayEditInActive: () => mayEdit,
        }),
    )!;
}

describe('useTaskCreate', () => {
    beforeEach(() => {
        post.mockReset();
        post.mockImplementation(((_url: string, _body: unknown, options: Options) => {
            options.onStart();
        }) as never);
        replace.mockReset();
        selected = ref(null);
        mayEdit = true;
        vi.useFakeTimers();
    });

    afterEach(() => {
        scopes.splice(0).forEach((scope) => scope.stop());
        vi.useRealTimers();
    });

    it('opens a draft with the global choice first and the active company second', () => {
        const creator = setup();

        creator.open();

        expect(creator.state.value).toBe('drafting');
        expect(creator.draft.tenantId).toBeNull();
        expect(creator.sessionKey.value).toBe(1);
        expect(creator.companyOptions.value).toEqual([
            { value: null, label: 'task_global' },
            { value: 'tA', label: 'Alfa' },
        ]);

        creator.open();

        expect(creator.sessionKey.value).toBe(2);
    });

    it('offers only the global choice when the active company is not in the context', () => {
        const creator = setup('tZ');

        creator.open();

        expect(creator.companyOptions.value).toEqual([{ value: null, label: 'task_global' }]);
    });

    it('does not create below the minimum length', () => {
        const creator = setup();
        creator.open();

        creator.type('Za');
        vi.advanceTimersByTime(CREATE_PAUSE_MS * 2);

        expect(CREATE_MIN_CHARS).toBe(3);
        expect(post).not.toHaveBeenCalled();
    });

    it('creates once after the pause with the draft body', () => {
        const creator = setup();
        creator.open();

        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS - 1);

        expect(post).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);

        expect(post).toHaveBeenCalledTimes(1);
        const { url, body, options } = call(0);
        expect(url).toBe('/tasks');
        expect(body).toEqual({ title: 'Zav', priority: 'normal', tenant_id: null });
        expect('status' in body).toBe(false);
        expect(options.async).toBe(true);
        expect(options.only).toEqual(TASK_CREATE_PROPS);
        expect(options.replace).toBe(false);
        expect(creator.state.value).toBe('creating');
    });

    it('restarts the pause on every keystroke', () => {
        const creator = setup();
        creator.open();

        creator.type('Zav');
        vi.advanceTimersByTime(500);
        creator.type('Zavo');
        vi.advanceTimersByTime(500);

        expect(post).not.toHaveBeenCalled();

        vi.advanceTimersByTime(300);

        expect(post).toHaveBeenCalledTimes(1);
        expect(call(0).body.title).toBe('Zavo');
    });

    it('sends one create when the pause, Enter and leave fire together', async () => {
        const creator = setup();
        creator.open();

        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);
        creator.createNow();
        const leaving = creator.leave();

        expect(post).toHaveBeenCalledTimes(1);

        let settled = false;
        void leaving.then(() => (settled = true));
        await nextTick();

        expect(settled).toBe(false);

        arrive(0);

        await expect(leaving).resolves.toBe(true);
        expect(post).toHaveBeenCalledTimes(1);
    });

    it('creates on Enter with a single character', () => {
        const creator = setup();
        creator.open();

        creator.type('Z');
        creator.createNow();

        expect(post).toHaveBeenCalledTimes(1);
        expect(call(0).body.title).toBe('Z');
    });

    it('selects the created task and hands over a title typed during the request', () => {
        const creator = setup();
        const handler = vi.fn();
        creator.onCreated(handler);
        creator.open();

        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);
        creator.type('Zavolať');
        arrive(0, 'n1');

        expect(creator.state.value).toBe('created');
        expect(creator.createdId.value).toBe('n1');
        expect(creator.firmLocked.value).toBe(true);
        expect(handler).toHaveBeenCalledTimes(1);
        expect(handler).toHaveBeenCalledWith('Zavolať');
    });

    it('hands over null when nothing was typed during the request', () => {
        const creator = setup();
        const handler = vi.fn();
        creator.onCreated(handler);
        creator.open();

        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);
        arrive(0);

        expect(handler).toHaveBeenCalledWith(null);
    });

    it('sends the column status', () => {
        const creator = setup();
        creator.open('waiting');

        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);

        expect(call(0).body).toEqual({ title: 'Zav', priority: 'normal', tenant_id: null, status: 'waiting' });
    });

    it('creates in the active company on Enter only when the user cannot edit tasks there', () => {
        mayEdit = false;
        const creator = setup();
        creator.open();
        creator.draft.tenantId = 'tA';

        expect(creator.autoCreate.value).toBe(false);

        creator.type('Zavolať');
        vi.advanceTimersByTime(CREATE_PAUSE_MS * 2);

        expect(post).not.toHaveBeenCalled();

        creator.createNow();

        expect(post).toHaveBeenCalledTimes(1);
        expect(call(0).body.tenant_id).toBe('tA');
    });

    it('creates after the pause in the active company when the user can edit tasks there', () => {
        const creator = setup();
        creator.open();
        creator.draft.tenantId = 'tA';

        creator.type('Zavolať');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);

        expect(call(0).body.tenant_id).toBe('tA');
    });

    it('holds the pause while the firm menu is open and re-arms it on release', () => {
        const creator = setup();
        creator.open();

        creator.type('Zav');
        creator.holdPause();
        vi.advanceTimersByTime(CREATE_PAUSE_MS * 2);

        expect(post).not.toHaveBeenCalled();

        creator.releasePause();
        vi.advanceTimersByTime(CREATE_PAUSE_MS);

        expect(post).toHaveBeenCalledTimes(1);
    });

    it('does not re-arm on release for a title below the minimum', () => {
        const creator = setup();
        creator.open();

        creator.type('Za');
        creator.holdPause();
        creator.releasePause();
        vi.advanceTimersByTime(CREATE_PAUSE_MS * 2);

        expect(post).not.toHaveBeenCalled();
    });

    it('keeps the title on a 422, shows it under the right field and retries on typing', () => {
        const creator = setup();
        creator.open();

        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);
        fail(0, (o) => o.onError({ title: 'Povinné', tenant_id: 'Zlá firma', priority: 'Zlá priorita' }));

        expect(creator.state.value).toBe('error');
        expect(creator.draft.title).toBe('Zav');
        expect(creator.errors.value).toEqual({ title: 'Povinné', tenant_id: 'Zlá firma', general: 'Zlá priorita' });

        creator.type('Zavo');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);

        expect(post).toHaveBeenCalledTimes(2);
        expect(creator.errors.value).toEqual({});
    });

    it('shows the generic message on a network error and forbidden on 403', () => {
        const creator = setup();
        creator.open();

        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);
        fail(0, (o) => o.onNetworkError());

        expect(creator.errors.value).toEqual({ general: 'task_create_failed' });

        creator.type('Zavo');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);
        call(1).options.onHttpException({ status: 403 });
        call(1).options.onFinish();

        expect(creator.errors.value).toEqual({ general: 'forbidden' });
        expect(creator.draft.title).toBe('Zavo');
    });

    it('closes when the created task is not visible in the panel', () => {
        const creator = setup();
        creator.open();

        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);
        arrive(0, 'n1', { selected_task: null });

        expect(creator.state.value).toBe('closed');
        expect(creator.createdId.value).toBeNull();
    });

    it('ends the session when the selected task changes away from the created one', async () => {
        const creator = setup();
        creator.open();

        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);
        selected.value = 'n1';
        arrive(0, 'n1');

        selected.value = 'other';
        await nextTick();

        expect(creator.state.value).toBe('closed');

        creator.open();
        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);
        selected.value = 'n2';
        arrive(1, 'n2');
        selected.value = null;
        await nextTick();

        expect(creator.state.value).toBe('closed');
    });

    it('keeps the session while the created task stays selected', async () => {
        const creator = setup();
        creator.open();

        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);
        arrive(0, 'n1');
        selected.value = 'n1';
        await nextTick();

        expect(creator.state.value).toBe('created');
    });

    it('creates without selecting on leave and cleans the url', async () => {
        const creator = setup();
        creator.open();
        creator.type('Za');

        const leaving = creator.leave();

        expect(post).toHaveBeenCalledTimes(1);
        const { options } = call(0);
        expect(options.only).toEqual(['tasks', 'board', 'flash']);
        expect(options.replace).toBe(true);

        arrive(0, 'n1');

        await expect(leaving).resolves.toBe(true);
        expect(replace).toHaveBeenCalledWith({
            url: '/tasks?view=board',
            preserveState: true,
            preserveScroll: true,
        });
        expect(creator.state.value).toBe('closed');
    });

    it('resolves false and keeps the title when a leave create fails', async () => {
        const creator = setup();
        creator.open();
        creator.type('Za');

        const leaving = creator.leave();
        fail(0, (o) => o.onNetworkError());

        await expect(leaving).resolves.toBe(false);
        expect(creator.state.value).toBe('error');
        expect(creator.draft.title).toBe('Za');
        expect(replace).not.toHaveBeenCalled();
    });

    it('sends nothing on leave or discard with an empty title', async () => {
        const creator = setup();
        creator.open();

        await expect(creator.leave()).resolves.toBe(true);
        expect(creator.state.value).toBe('closed');

        creator.open();
        creator.type('   ');
        creator.discard();
        vi.advanceTimersByTime(CREATE_PAUSE_MS * 2);

        expect(creator.state.value).toBe('closed');
        expect(post).not.toHaveBeenCalled();
    });

    it('discards a titled draft without a request and cancels the pause', () => {
        const creator = setup();
        creator.open();

        creator.type('Zav');
        creator.discard();
        vi.advanceTimersByTime(CREATE_PAUSE_MS * 2);

        expect(post).not.toHaveBeenCalled();
        expect(creator.state.value).toBe('closed');
    });

    it('settles to true without a request when closed or created, and creates for a titled draft', async () => {
        await expect(settleTaskCreate()).resolves.toBe(true);

        const creator = setup();

        await expect(settleTaskCreate()).resolves.toBe(true);

        creator.open();
        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);
        arrive(0);

        await expect(settleTaskCreate()).resolves.toBe(true);
        expect(post).toHaveBeenCalledTimes(1);

        creator.open();
        creator.type('Ab');
        const settling = settleTaskCreate();

        expect(post).toHaveBeenCalledTimes(2);

        arrive(1, 'n2');

        await expect(settling).resolves.toBe(true);
    });

    it('fails a create whose visit never started and lets a settle resolve false', async () => {
        post.mockImplementation((() => undefined) as never);
        const creator = setup();
        creator.open();
        creator.type('Za');

        const leaving = creator.leave();

        await expect(leaving).resolves.toBe(false);
        expect(creator.state.value).toBe('error');
        expect(creator.errors.value).toEqual({ general: 'task_create_failed' });
        expect(creator.draft.title).toBe('Za');

        post.mockImplementation(((_u: string, _b: unknown, options: Options) => options.onStart()) as never);
        creator.type('Zav');
        vi.advanceTimersByTime(CREATE_PAUSE_MS);

        expect(post).toHaveBeenCalledTimes(2);
    });

    it('re-arms the pause when the company changes', () => {
        mayEdit = false;
        const creator = setup();
        creator.open();
        creator.type('Zavolať');

        creator.setCompany('tA');
        vi.advanceTimersByTime(CREATE_PAUSE_MS * 2);

        expect(post).not.toHaveBeenCalled();

        creator.setCompany(null);
        vi.advanceTimersByTime(CREATE_PAUSE_MS);

        expect(post).toHaveBeenCalledTimes(1);
        expect(call(0).body.tenant_id).toBeNull();
    });

    it('does not rewrite the url after a leave create when the page is already gone', async () => {
        const scope = effectScope();
        const creator = scope.run(() =>
            useTaskCreate({
                context: () => context,
                activeTenantId: () => 'tA',
                selectedTaskId: () => null,
                mayEditInActive: () => true,
            }),
        )!;
        creator.open();
        creator.type('Za');

        const leaving = creator.leave();
        scope.stop();
        arrive(0, 'n1');

        await expect(leaving).resolves.toBe(true);
        expect(replace).not.toHaveBeenCalled();
    });
});
