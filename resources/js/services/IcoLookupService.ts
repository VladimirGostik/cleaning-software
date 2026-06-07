export class IcoLookupService {
    public lookup(ico: string, signal?: AbortSignal): Promise<App.Data.Tenants.IcoLookupData> {
        return window.axios
            .get<App.Data.Tenants.IcoLookupData>(`/api/icos/${ico}`, { signal })
            .then((r) => r.data);
    }
}
