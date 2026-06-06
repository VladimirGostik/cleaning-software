/**
 * MeService — thin wrapper over GET /api/me.
 *
 * Uses window.axios (configured in bootstrap.ts with CSRF + session-cookie auth).
 * This is the only HTTP call in the capabilities layer.
 */
export class MeService {
    public fetchMe(): Promise<App.Data.Auth.MeData> {
        return window.axios.get<App.Data.Auth.MeData>('/api/me').then((r) => r.data);
    }
}
