import { onBeforeUnmount, onMounted, reactive, readonly, type DeepReadonly } from 'vue';

// ADR — module-singleton composable, not a Pinia store (project has no Pinia).
// State + interval + AbortController live at module scope; every mounted consumer
// (sidebar bell, mobile navbar bell, notifications index page) shares the same instance
// via ref-counted subscribers. Polling starts at the first subscriber, stops at zero.
// This replaces `main`'s Pinia `notifications` store 1:1.

export interface NotificationBellState {
    unreadCount: number;
    recent: App.Data.Notifications.NotificationListItemData[];
    loading: boolean;
    error: boolean;
    stopped: boolean;
}

const POLL_INTERVAL_MS = 60_000;
const BELL_URL = '/notifications/bell';

const state = reactive<NotificationBellState>({
    unreadCount: 0,
    recent: [],
    loading: false,
    error: false,
    stopped: false,
});

let subscribers = 0;
let timer: number | null = null;
let controller: AbortController | null = null;
let inflight: Promise<void> | null = null;

// Own-controller identity discipline (same as useJobCalendar.ts): a call only mutates
// shared state / resets `inflight` if no newer fetchBell() has superseded it in the
// meantime (e.g. stop() aborting on unmount while a fresh subscriber already re-fetched).
async function fetchBell(): Promise<void> {
    controller?.abort();
    const ownController = new AbortController();
    controller = ownController;

    try {
        const response = await fetch(BELL_URL, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: ownController.signal,
        });

        if (ownController.signal.aborted) return;

        if (!response.ok) {
            if (response.status === 401 || response.status === 403 || response.status === 419) {
                state.stopped = true;
                state.loading = false;
                stop();
                return;
            }
            state.error = true;
            return;
        }

        const payload = (await response.json()) as App.Data.Notifications.NotificationBellData;

        if (ownController.signal.aborted) return;

        state.unreadCount = payload.unread_count;
        state.recent = payload.recent;
        state.error = false;
    } catch (err) {
        if (err instanceof DOMException && err.name === 'AbortError') return;
        state.error = true;
    } finally {
        if (controller === ownController) {
            state.loading = false;
            inflight = null;
            controller = null;
        }
    }
}

function refresh(): Promise<void> {
    if (state.stopped) return Promise.resolve();
    if (inflight) return inflight;

    state.loading = state.recent.length === 0 && !state.error;
    inflight = fetchBell();
    return inflight;
}

function clearTimer(): void {
    if (timer !== null) {
        window.clearInterval(timer);
        timer = null;
    }
}

function startTimer(): void {
    clearTimer();
    if (document.visibilityState !== 'visible') return;
    timer = window.setInterval(() => void refresh(), POLL_INTERVAL_MS);
}

function onVisibilityChange(): void {
    if (document.visibilityState === 'hidden') {
        clearTimer();
    } else {
        void refresh();
        startTimer();
    }
}

function start(): void {
    document.addEventListener('visibilitychange', onVisibilityChange);
    startTimer();
}

function stop(): void {
    clearTimer();
    document.removeEventListener('visibilitychange', onVisibilityChange);
    controller?.abort();
    controller = null;
    inflight = null;
}

function markReadLocally(id: string): void {
    const index = state.recent.findIndex((n) => n.id === id);
    if (index === -1) return;
    const notification = state.recent[index];
    if (notification.read_at !== null) return;

    state.recent[index] = { ...notification, read_at: new Date().toISOString() };
    state.unreadCount = Math.max(0, state.unreadCount - 1);
}

function markAllReadLocally(): void {
    const now = new Date().toISOString();
    state.recent = state.recent.map((n) => (n.read_at === null ? { ...n, read_at: now } : n));
    state.unreadCount = 0;
}

export function useNotificationBell(): {
    state: DeepReadonly<NotificationBellState>;
    refresh: () => Promise<void>;
    markReadLocally: (id: string) => void;
    markAllReadLocally: () => void;
} {
    onMounted(() => {
        subscribers += 1;
        if (subscribers === 1) start();
        void refresh();
    });

    onBeforeUnmount(() => {
        subscribers -= 1;
        if (subscribers === 0) stop();
    });

    return { state: readonly(state), refresh, markReadLocally, markAllReadLocally };
}
