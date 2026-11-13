import { computed, nextTick, onScopeDispose, ref, shallowRef, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { buildTaskFeed, createdEntry } from '@/utils/taskFeed';
import type { FeedItem, FeedSort, FeedView } from '@/utils/taskFeed';

const SORT_STORAGE_KEY = 'kg.taskFeedSort';
const VIEW_STORAGE_PREFIX = 'kg.taskFeedView.';
const REFRESH_DEBOUNCE_MS = 500;
const CACHE_LIMIT = 20;

interface Options {
    taskId: string;
    activity: () => App.Data.Tasks.TaskActivityFeedData | null | undefined;
    task: () => App.Data.Tasks.TaskDetailData;
    thread: () => App.Data.Tasks.TaskThreadData;
    attachments: () => App.Data.Tasks.TaskAttachmentListData;
}

interface TaskActivity {
    view: Ref<FeedView>;
    sort: Ref<FeedSort>;
    feed: ComputedRef<FeedItem[]>;
    loading: Ref<boolean>;
    failed: Ref<boolean>;
    setView: (view: FeedView) => void;
    toggleSort: () => void;
    retry: () => void;
}

function readSort(): FeedSort {
    try {
        return window.localStorage.getItem(SORT_STORAGE_KEY) === 'newest' ? 'newest' : 'oldest';
    } catch {
        return 'oldest';
    }
}

const loadedByTask = new Map<string, App.Data.Tasks.TaskActivityFeedData>();

export function clearTaskActivityCache(): void {
    loadedByTask.clear();
}

function remember(taskId: string, feed: App.Data.Tasks.TaskActivityFeedData): void {
    loadedByTask.delete(taskId);
    loadedByTask.set(taskId, feed);

    for (const oldest of loadedByTask.keys()) {
        if (loadedByTask.size <= CACHE_LIMIT) break;
        loadedByTask.delete(oldest);
    }
}

function readView(taskId: string): FeedView {
    try {
        return window.sessionStorage.getItem(VIEW_STORAGE_PREFIX + taskId) === 'all' ? 'all' : 'comments';
    } catch {
        return 'comments';
    }
}

function writeView(taskId: string, view: FeedView): void {
    try {
        window.sessionStorage.setItem(VIEW_STORAGE_PREFIX + taskId, view);
    } catch {
        return;
    }
}

function writeSort(sort: FeedSort): void {
    try {
        window.localStorage.setItem(SORT_STORAGE_KEY, sort);
    } catch {
        return;
    }
}

export function useTaskActivity(options: Options): TaskActivity {
    const view = ref<FeedView>(readView(options.taskId));
    const sort = ref<FeedSort>(readSort());
    const cached = loadedByTask.get(options.taskId) ?? null;
    const loaded = shallowRef<App.Data.Tasks.TaskActivityFeedData | null>(cached);
    const loading = ref(false);
    const failed = ref(false);

    let disposed = false;
    let requested = false;
    let stale = false;
    let timer: ReturnType<typeof setTimeout> | null = null;

    function accept(value: App.Data.Tasks.TaskActivityFeedData | null | undefined): void {
        if (disposed || value === undefined) return;

        loaded.value = value;

        if (value === null) loadedByTask.delete(options.taskId);
        else remember(options.taskId, value);
    }

    watch(options.activity, (value) => {
        if (requested) accept(value);
    });

    function load(background: boolean): void {
        if (!background) {
            loading.value = true;
            failed.value = false;
        }

        const fail = (): boolean => {
            if (!background) failed.value = true;

            return false;
        };

        router.reload({
            only: ['selected_activity'],
            preserveState: true,
            preserveScroll: true,
            async: true,
            onError: fail,
            onHttpException: fail,
            onNetworkError: fail,
            onSuccess: () => {
                void nextTick().then(() => accept(options.activity()));
            },
            onFinish: () => {
                if (!background) void nextTick().then(() => (loading.value = false));
            },
        });
    }

    function clearTimer(): void {
        if (timer !== null) clearTimeout(timer);
        timer = null;
    }

    watch(
        () => [options.task(), options.thread(), options.attachments()],
        () => {
            if (!requested) return;

            if (view.value !== 'all') {
                stale = true;

                return;
            }

            clearTimer();
            timer = setTimeout(() => load(true), REFRESH_DEBOUNCE_MS);
        },
    );

    onScopeDispose(() => {
        disposed = true;
        clearTimer();
    });

    function setView(next: FeedView): void {
        view.value = next;
        writeView(options.taskId, next);

        if (next !== 'all') {
            clearTimer();

            return;
        }

        if (!requested) {
            requested = true;
            load(false);
        } else if (stale) {
            stale = false;
            load(true);
        }
    }

    function toggleSort(): void {
        sort.value = sort.value === 'oldest' ? 'newest' : 'oldest';
        writeSort(sort.value);
    }

    if (view.value === 'all') {
        requested = true;
        load(loaded.value !== null);
    }

    const feed = computed<FeedItem[]>(() => {
        const comments = options.thread().comments;

        if (view.value === 'comments') {
            return buildTaskFeed({
                comments,
                entries: [createdEntry(options.task())],
                hasOlder: false,
                sort: sort.value,
            });
        }

        return buildTaskFeed({
            comments,
            entries: loaded.value?.entries ?? [],
            hasOlder: loaded.value?.has_older ?? false,
            sort: sort.value,
        });
    });

    return { view, sort, feed, loading, failed, setView, toggleSort, retry: () => load(false) };
}
