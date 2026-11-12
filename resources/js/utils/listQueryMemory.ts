const PREFIX = 'kg:list-query:';
const ALLOWED = new Set(['sort', 'page', 'per_page', 'view']);

function isAllowed(name: string): boolean {
    return ALLOWED.has(name) || name.startsWith('filter[');
}

function readable(params: URLSearchParams): string {
    return params.toString().replace(/%5B/gi, '[').replace(/%5D/gi, ']');
}

function splitHref(href: string): { path: string; search: string; hash: string } {
    const hashAt = href.indexOf('#');
    const hash = hashAt === -1 ? '' : href.slice(hashAt);
    const rest = hashAt === -1 ? href : href.slice(0, hashAt);
    const queryAt = rest.indexOf('?');
    return {
        path: queryAt === -1 ? rest : rest.slice(0, queryAt),
        search: queryAt === -1 ? '' : rest.slice(queryAt + 1),
        hash,
    };
}

export function pickListParams(search: string): URLSearchParams {
    const picked = new URLSearchParams();
    new URLSearchParams(search).forEach((value, name) => {
        if (isAllowed(name) && value !== '') {
            picked.append(name, value);
        }
    });
    return picked;
}

export function storageKey(tenantId: string, path: string): string {
    return `${PREFIX}${tenantId}:${path}`;
}

function defaultStorage(): Storage | null {
    return typeof window === 'undefined' ? null : window.sessionStorage;
}

export function save(
    tenantId: string,
    url: string,
    listPaths: string[],
    storage: Storage | null = defaultStorage(),
): void {
    const { path, search } = splitHref(url);
    if (!storage || !listPaths.includes(path)) return;

    const query = readable(pickListParams(search));
    const key = storageKey(tenantId, path);
    if (query === '') {
        storage.removeItem(key);
        return;
    }
    storage.setItem(key, query);
}

export function restore(
    tenantId: string,
    href: string,
    listPaths: string[],
    storage: Storage | null = defaultStorage(),
): string {
    const { path, search, hash } = splitHref(href);
    if (!storage || search !== '' || !listPaths.includes(path)) return href;

    const stored = storage.getItem(storageKey(tenantId, path));
    return stored ? `${path}?${stored}${hash}` : href;
}

export function clearAll(storage: Storage | null = defaultStorage()): void {
    if (!storage) return;
    const keys: string[] = [];
    for (let i = 0; i < storage.length; i++) {
        const key = storage.key(i);
        if (key?.startsWith(PREFIX)) keys.push(key);
    }
    keys.forEach((key) => storage.removeItem(key));
}
