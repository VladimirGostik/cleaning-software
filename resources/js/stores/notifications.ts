import { defineStore } from 'pinia';
import { notificationBellService } from '@/services';

/**
 * Notifications store — single source of truth for the bell unread count
 * and recent notification list.
 *
 * Source: GET /api/notifications/bell (polled every 60 s + refreshed on navigate).
 *
 * NOTE: UX-only layer. Real enforcement lives on the BE (policies + middleware).
 *
 * Call sites:
 *  - `fetchBell()` — called by the Inertia navigate hook on every authed nav.
 *  - `startPolling(ms)` — idempotent, started in the navigate hook.
 *  - `markReadLocally(id)` — optimistic update when user clicks a notification.
 *  - `reset()` — call on logout (AppLayout.vue).
 *
 * `pollHandle` is stored as plain state (not a Vue ref — intentional; the
 * ESLint no-restricted-syntax ban applies to ref() for app logic, not to
 * store state which is already reactive via Pinia Options API).
 */
export const useNotificationsStore = defineStore('notifications', {
    state: () => ({
        unreadCount: 0,
        recent: [] as App.Data.Notifications.NotificationListItemData[],
        loading: false,
        error: false,
        pollHandle: null as number | null,
    }),

    actions: {
        /** Fetch /api/notifications/bell and populate unreadCount + recent. */
        async fetchBell(): Promise<void> {
            this.loading = true;
            this.error = false;
            try {
                const data = await notificationBellService.fetchBell();
                this.unreadCount = data.unreadCount;
                this.recent = data.recent;
            } catch {
                this.error = true;
            } finally {
                this.loading = false;
            }
        },

        /** Idempotent: starts a polling interval if not already running. */
        startPolling(ms = 60000): void {
            if (this.pollHandle !== null) return;
            this.pollHandle = window.setInterval(() => {
                void this.fetchBell();
            }, ms) as unknown as number;
        },

        /** Stops the polling interval and clears the handle. */
        stopPolling(): void {
            if (this.pollHandle !== null) {
                window.clearInterval(this.pollHandle);
                this.pollHandle = null;
            }
        },

        /**
         * Optimistic update: mark a single notification as read locally.
         * Decrements unreadCount (floor 0) and stamps readAt on the item.
         */
        markReadLocally(id: string): void {
            this.recent = this.recent.map((n) =>
                n.id === id ? { ...n, readAt: new Date().toISOString() } : n,
            );
            this.unreadCount = Math.max(0, this.unreadCount - 1);
        },

        /** Clear on logout — stops polling then resets all state. */
        reset(): void {
            this.stopPolling();
            this.$reset();
        },
    },
});
