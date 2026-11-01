import { computed, onScopeDispose, ref, watch, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { HttpError, getJson } from '@/utils/http';
import { runChangeReloadProps } from '@/utils/taskAi';

const POLL_INTERVAL_MS = 3000;
const MAX_BACKOFF_MS = 60_000;
const FATAL_STATUSES = [401, 403, 404, 419];

export function useTaskAiRun(options: { taskId: string; thread: () => App.Data.Tasks.TaskThreadData }): {
    starting: Ref<boolean>;
    errors: Ref<Record<string, string>>;
    statusCheckFailed: Ref<boolean>;
    start: (companyId: string | null) => void;
} {
    const { t } = useI18n();
    const starting = ref(false);
    const errors = ref<Record<string, string>>({});
    const statusCheckFailed = ref(false);

    const activeRunId = computed(() => {
        const run = options.thread().latest_run;

        return run?.is_active ? run.id : null;
    });

    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;
    let busy = false;
    let halted = false;
    let failures = 0;

    function clearTimer(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function schedule(): void {
        clearTimer();

        if (activeRunId.value === null || halted || busy || document.hidden) {
            return;
        }

        timer = setTimeout(() => void tick(), Math.min(POLL_INTERVAL_MS * 2 ** failures, MAX_BACKOFF_MS));
    }

    function reloadThread(status: App.Enums.TaskRunStatusEnum | null): void {
        busy = true;
        router.reload({
            only: runChangeReloadProps(status),
            async: true,
            onSuccess: () => {
                failures = 0;
            },
            onHttpException: () => {
                failures += 1;

                return false;
            },
            onNetworkError: () => {
                failures += 1;

                return false;
            },
            onFinish: () => {
                busy = false;
                schedule();
            },
        });
    }

    function hasChanged(polled: App.Data.Tasks.TaskRunStatusData): boolean {
        const current = options.thread().latest_run;

        return polled.latest_run_id !== (current?.id ?? null) || polled.status !== (current?.status ?? null);
    }

    async function tick(): Promise<void> {
        if (activeRunId.value === null || busy) {
            return;
        }

        busy = true;
        const own = new AbortController();
        controller = own;

        try {
            const polled = await getJson<App.Data.Tasks.TaskRunStatusData>(`/tasks/${options.taskId}/ai-run-status`, {
                signal: own.signal,
            });
            statusCheckFailed.value = false;
            failures = 0;
            busy = false;

            if (hasChanged(polled)) {
                reloadThread(polled.status);
            } else if (polled.is_active) {
                schedule();
            }
        } catch (error) {
            busy = false;

            if (own.signal.aborted) {
                return;
            }

            statusCheckFailed.value = true;
            failures += 1;
            halted = error instanceof HttpError && FATAL_STATUSES.includes(error.status);
            schedule();
        } finally {
            if (controller === own) {
                controller = null;
            }
        }
    }

    function onVisibilityChange(): void {
        if (document.hidden) {
            clearTimer();
            controller?.abort();

            return;
        }

        if (activeRunId.value !== null && !halted && !busy) {
            clearTimer();
            void tick();
        }
    }

    watch(
        activeRunId,
        () => {
            halted = false;
            failures = 0;
            statusCheckFailed.value = false;
            schedule();
        },
        { immediate: true },
    );

    document.addEventListener('visibilitychange', onVisibilityChange);

    onScopeDispose(() => {
        clearTimer();
        controller?.abort();
        document.removeEventListener('visibilitychange', onVisibilityChange);
    });

    function start(companyId: string | null): void {
        router.post(
            `/tasks/${options.taskId}/ai-runs`,
            { company_id: companyId },
            {
                only: ['thread', 'flash'],
                preserveState: true,
                preserveScroll: true,
                onStart: () => {
                    starting.value = true;
                    errors.value = {};
                },
                onError: (bag: Record<string, string>) => {
                    errors.value = bag;
                },
                onHttpException: () => {
                    errors.value = { request: t('task_ai_start_failed') };

                    return false;
                },
                onNetworkError: () => {
                    errors.value = { request: t('task_ai_start_failed') };

                    return false;
                },
                onFinish: () => {
                    starting.value = false;
                },
            },
        );
    }

    return { starting, errors, statusCheckFailed, start };
}
