/**
 * NotificationBellService — thin wrapper over GET /api/notifications/bell.
 *
 * Uses window.axios (configured in bootstrap.ts with CSRF + session-cookie auth).
 */
export class NotificationBellService {
    public fetchBell(): Promise<App.Data.Notifications.NotificationBellData> {
        return window.axios
            .get<App.Data.Notifications.NotificationBellData>('/api/notifications/bell')
            .then((r) => r.data);
    }
}
