import { computed, getCurrentScope, onScopeDispose, reactive, ref, watch, type ComputedRef, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { TASK_REFRESH_PROPS } from '@/Composables/useTaskInlineEdit';

export const CREATE_PAUSE_MS = 800;
export const CREATE_MIN_CHARS = 3;
export const TASK_CREATE_PROPS = [...TASK_REFRESH_PROPS, 'selected_attachments', 'selected_thread'];

const LEAVE_PROPS = ['tasks', 'board', 'flash'];

export type TaskCreateState = 'closed' | 'drafting' | 'creating' | 'error' | 'created';
export type TaskCreateErrors = Partial<Record<'title' | 'tenant_id' | 'general', string>>;
export interface TaskCompanyOption {
    value: string | null;
    label: string;
}

type CreateMode = 'select' | 'leave';
type CreatedPage = { url: string; props: Record<string, unknown> };

export interface TaskCreateSession {
    state: Ref<TaskCreateState>;
    sessionKey: Ref<number>;
    createdId: Ref<string | null>;
    draft: { title: string; tenantId: string | null; status: App.Enums.TaskStatusEnum | null };
    errors: Ref<TaskCreateErrors>;
    companyOptions: ComputedRef<TaskCompanyOption[]>;
    firmLocked: ComputedRef<boolean>;
    autoCreate: ComputedRef<boolean>;
    open: (status?: App.Enums.TaskStatusEnum | null) => void;
    type: (title: string) => void;
    holdPause: () => void;
    releasePause: () => void;
    setCompany: (tenantId: string | null) => void;
    createNow: () => void;
    leave: () => Promise<boolean>;
    discard: () => void;
    onCreated: (handler: (pendingTitle: string | null) => void) => () => void;
}

let activeLeave: (() => Promise<boolean>) | null = null;

export function settleTaskCreate(): Promise<boolean> {
    return activeLeave ? activeLeave() : Promise.resolve(true);
}

function taskParam(url: string): string | null {
    return new URL(url, window.location.origin).searchParams.get('task');
}

function withoutTask(url: string): string {
    const target = new URL(url, window.location.origin);
    target.searchParams.delete('task');

    return `${target.pathname}${target.search}`;
}

export function useTaskCreate(options: {
    context: () => App.Data.Tasks.TaskFormContextData;
    activeTenantId: () => string | null;
    selectedTaskId: () => string | null;
    mayEditInActive: () => boolean;
}): TaskCreateSession {
    const { t } = useI18n();

    const state = ref<TaskCreateState>('closed');
    const sessionKey = ref(0);
    const createdId = ref<string | null>(null);
    const errors = ref<TaskCreateErrors>({});
    const draft = reactive<TaskCreateSession['draft']>({ title: '', tenantId: null, status: null });

    let timer: ReturnType<typeof setTimeout> | null = null;
    let held = false;
    let inflight: Promise<boolean> | null = null;
    let sentTitle = '';
    let epoch = 0;
    let disposed = false;
    let handler: ((pendingTitle: string | null) => void) | null = null;

    const companyOptions = computed<TaskCompanyOption[]>(() => {
        const list: TaskCompanyOption[] = [{ value: null, label: t('task_global') }];
        const activeId = options.activeTenantId();
        const active = activeId ? options.context().companies.find((c) => c.tenant_id === activeId) : undefined;

        if (active) list.push({ value: active.tenant_id, label: active.name });

        return list;
    });

    const firmLocked = computed(() => state.value === 'creating' || state.value === 'created');
    const autoCreate = computed(() => draft.tenantId === null || options.mayEditInActive());

    function clearTimer(): void {
        if (timer === null) return;

        clearTimeout(timer);
        timer = null;
    }

    function titled(): boolean {
        return draft.title.trim() !== '';
    }

    function arm(): void {
        clearTimer();

        if (held || !autoCreate.value) return;
        if (state.value !== 'drafting' && state.value !== 'error') return;
        if (draft.title.trim().length < CREATE_MIN_CHARS) return;

        timer = setTimeout(() => {
            timer = null;
            void create('select');
        }, CREATE_PAUSE_MS);
    }

    function endSession(): void {
        clearTimer();
        epoch += 1;
        held = false;
        state.value = 'closed';
        createdId.value = null;
        errors.value = {};
        draft.title = '';
        draft.tenantId = null;
        draft.status = null;
    }

    function failure(received: Record<string, string>): TaskCreateErrors {
        const next: TaskCreateErrors = {};
        const rest = Object.entries(received).filter(([key]) => key !== 'title' && key !== 'tenant_id');

        if (received.title !== undefined) next.title = received.title;
        if (received.tenant_id !== undefined) next.tenant_id = received.tenant_id;
        if (rest.length > 0) next.general = rest[0][1];

        return next;
    }

    function succeed(mode: CreateMode, page: CreatedPage, title: string): boolean {
        if (mode === 'leave') {
            if (!disposed) {
                router.replace({ url: withoutTask(page.url), preserveState: true, preserveScroll: true });
            }

            endSession();

            return true;
        }

        const id = taskParam(page.url);
        const selected = page.props.selected_task as { id: string } | null | undefined;

        if (!id || selected?.id !== id) {
            endSession();

            return true;
        }

        const latest = draft.title.trim();

        createdId.value = id;
        state.value = 'created';
        errors.value = {};
        handler?.(latest !== '' && latest !== title ? latest : null);

        return true;
    }

    function create(mode: CreateMode): Promise<boolean> {
        if (inflight) return inflight;

        const title = draft.title.trim();
        const startedIn = epoch;
        let ok = false;
        let problem: TaskCreateErrors | null = null;

        sentTitle = title;
        state.value = 'creating';
        errors.value = {};
        clearTimer();

        const body: Record<string, unknown> = { title, priority: 'normal', tenant_id: draft.tenantId };
        if (draft.status !== null) body.status = draft.status;

        const generic = (): TaskCreateErrors => ({ general: t('task_create_failed') });

        let resolve: (value: boolean) => void = () => undefined;
        const promise = new Promise<boolean>((done) => {
            resolve = done;
        });
        inflight = promise;

        {
            let started = false;
            let finished = false;

            const finish = (): void => {
                if (finished) return;

                finished = true;
                inflight = null;

                if (!ok && startedIn === epoch) {
                    state.value = 'error';
                    errors.value = problem ?? generic();
                }

                resolve(ok);
            };

            router.post('/tasks', body, {
                async: true,
                preserveState: true,
                preserveScroll: true,
                only: mode === 'select' ? TASK_CREATE_PROPS : LEAVE_PROPS,
                replace: mode === 'leave',
                onStart: () => {
                    started = true;
                },
                onSuccess: (page: CreatedPage) => {
                    ok = true;
                    if (startedIn === epoch) succeed(mode, page, sentTitle);
                },
                onError: (received: Record<string, string>) => {
                    problem = failure(received);
                },
                onHttpException: (response: { status: number }) => {
                    problem = response.status === 403 ? { general: t('forbidden') } : generic();

                    return false;
                },
                onNetworkError: () => {
                    problem = generic();
                },
                onCancel: () => {
                    problem = generic();
                },
                onFinish: finish,
            });

            if (!started) {
                problem = generic();
                finish();
            }
        }

        return promise;
    }

    function open(status: App.Enums.TaskStatusEnum | null = null): void {
        endSession();
        sessionKey.value += 1;
        draft.status = status;
        state.value = 'drafting';
    }

    function type(title: string): void {
        draft.title = title;

        if (state.value === 'drafting' || state.value === 'error') arm();
    }

    function holdPause(): void {
        held = true;
        clearTimer();
    }

    function releasePause(): void {
        held = false;
        arm();
    }

    function setCompany(tenantId: string | null): void {
        draft.tenantId = tenantId;
        arm();
    }

    function createNow(): void {
        clearTimer();

        if ((state.value === 'drafting' || state.value === 'error') && titled()) void create('select');
    }

    async function leave(): Promise<boolean> {
        clearTimer();

        if (state.value === 'closed' || state.value === 'created') return true;
        if (inflight) return inflight;

        if (!titled()) {
            endSession();

            return true;
        }

        return create('leave');
    }

    function discard(): void {
        endSession();
    }

    function onCreated(next: (pendingTitle: string | null) => void): () => void {
        handler = next;

        return () => {
            if (handler === next) handler = null;
        };
    }

    watch(options.selectedTaskId, (id) => {
        if (state.value === 'created' && id !== createdId.value) endSession();
    });

    activeLeave = leave;

    if (getCurrentScope()) {
        onScopeDispose(() => {
            disposed = true;
            clearTimer();
            if (activeLeave === leave) activeLeave = null;
        });
    }

    return {
        state,
        sessionKey,
        createdId,
        draft,
        errors,
        companyOptions,
        firmLocked,
        autoCreate,
        open,
        type,
        holdPause,
        releasePause,
        setCompany,
        createNow,
        leave,
        discard,
        onCreated,
    };
}
