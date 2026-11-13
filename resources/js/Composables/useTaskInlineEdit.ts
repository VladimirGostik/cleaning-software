import { getCurrentScope, onScopeDispose, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/Composables/useToast';

export type TaskPatch = Partial<
    Pick<App.Data.Tasks.TaskPatchData, 'title' | 'priority' | 'description' | 'due_at' | 'remind_on' | 'remind_slot'>
>;
export type TaskPatchErrors = Partial<Record<keyof TaskPatch, string>>;
export type SaveState = 'idle' | 'saving' | 'saved' | 'error';

export const SAVE_DEBOUNCE_MS = 1500;

export const TASK_REFRESH_PROPS = ['tasks', 'board', 'selected_task', 'flash'];

let activeFlush: (() => Promise<void>) | null = null;
let activeIdle: (() => boolean) | null = null;

export function flushActiveTaskEdit(): Promise<void> {
    return activeFlush ? activeFlush() : Promise.resolve();
}

export function taskEditIdle(): boolean {
    return activeIdle ? activeIdle() : true;
}

let runningAfterEdits = false;

export async function runAfterTaskEdits(action: () => void): Promise<void> {
    if (runningAfterEdits) return;

    runningAfterEdits = true;
    try {
        if (!taskEditIdle()) await flushActiveTaskEdit();
    } finally {
        runningAfterEdits = false;
    }

    action();
}

function hasKeys(value: object): boolean {
    return Object.keys(value).length > 0;
}

export function useTaskInlineEdit(options: { taskId: string | (() => string | null) }) {
    const { t } = useI18n();
    const toast = useToast();

    const state = ref<SaveState>('idle');
    const errors = ref<TaskPatchErrors>({});
    const resets = ref(0);

    let queued: TaskPatch = {};
    let delayed: TaskPatch = {};
    let timer: ReturnType<typeof setTimeout> | null = null;
    let inFlight = false;
    let waiters: Array<() => void> = [];

    function promote(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }

        queued = { ...queued, ...delayed };
        delayed = {};
    }

    function settle(): void {
        const done = waiters;
        waiters = [];
        done.forEach((resolve) => resolve());
    }

    function send(): void {
        if (inFlight) return;

        if (!hasKeys(queued)) {
            settle();
            return;
        }

        const taskId = typeof options.taskId === 'function' ? options.taskId() : options.taskId;

        if (taskId === null) return;

        const patch = queued;
        const keys = Object.keys(patch) as Array<keyof TaskPatch>;
        queued = {};
        inFlight = true;
        state.value = 'saving';

        let started = false;
        let succeeded = false;
        let lost = false;
        let finished = false;

        const finish = (): void => {
            if (finished) return;

            finished = true;
            inFlight = false;

            const hadNewer = hasKeys(queued);

            if (lost) {
                const requeue: TaskPatch = {};
                keys.forEach((key) => {
                    if (!(key in queued) && !(key in delayed)) Object.assign(requeue, { [key]: patch[key] });
                });
                queued = { ...requeue, ...queued };
            }

            state.value = succeeded && !hasKeys(errors.value) ? 'saved' : 'error';

            if (hadNewer) {
                send();
                return;
            }

            settle();
        };

        router.patch(
            `/tasks/${taskId}`,
            { ...patch },
            {
                async: true,
                preserveState: true,
                preserveScroll: true,
                only: TASK_REFRESH_PROPS,
                onStart: () => {
                    started = true;
                },
                onSuccess: () => {
                    succeeded = true;
                    const rest = { ...errors.value };
                    keys.forEach((key) => delete rest[key]);
                    errors.value = rest;
                },
                onError: (received: Record<string, string>) => {
                    const next = { ...errors.value };
                    keys.forEach((key) => {
                        if (received[key] !== undefined) {
                            next[key] = received[key];
                        } else {
                            delete next[key];
                        }
                    });
                    errors.value = next;
                },
                onHttpException: (response: { status: number }) => {
                    if (response.status !== 403) {
                        lost = true;
                        return;
                    }

                    queued = {};
                    delayed = {};
                    resets.value += 1;
                    toast.error(t('forbidden'));

                    return false;
                },
                onNetworkError: () => {
                    lost = true;
                },
                onCancel: () => {
                    lost = true;
                },
                onFinish: finish,
            },
        );

        if (!started) {
            lost = true;
            finish();
        }
    }

    function save(patch: TaskPatch): void {
        promote();
        queued = { ...queued, ...patch };
        send();
    }

    function saveDebounced(patch: TaskPatch): void {
        delayed = { ...delayed, ...patch };

        if (timer !== null) clearTimeout(timer);

        timer = setTimeout(() => {
            promote();
            send();
        }, SAVE_DEBOUNCE_MS);
    }

    function flush(): Promise<void> {
        promote();
        send();

        if (!inFlight) return Promise.resolve();

        return new Promise((resolve) => waiters.push(resolve));
    }

    activeFlush = flush;
    activeIdle = () => !inFlight && timer === null && !hasKeys(queued) && !hasKeys(delayed);

    if (getCurrentScope()) {
        onScopeDispose(() => {
            promote();
            send();

            if (activeFlush === flush) {
                activeFlush = null;
                activeIdle = null;
            }
        });
    }

    return { state, errors, resets, save, saveDebounced, flush };
}
