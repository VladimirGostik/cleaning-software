import { router } from '@inertiajs/vue3';

export type QueryValue = string | number | boolean | null | undefined;

export interface VisitOptions {
    routeUrl?: string;
    only?: string[];
    replace?: boolean;
    resetPage?: boolean;
}

function currentUrl(routeUrl?: string): URL {
    return new URL(routeUrl ?? window.location.href, window.location.origin);
}

export function readSpatieQuery() {
    const params = new URL(window.location.href).searchParams;

    const filters: Record<string, string> = {};

    params.forEach((value, key) => {
        const match = key.match(/^filter\[(.+)]$/);

        if (match?.[1]) {
            filters[match[1]] = value;
        }
    });

    return {
        filters,
        sort: params.get('sort') ?? '',
        perPage: Number(params.get('per_page') ?? params.get('perPage') ?? 25),
        page: Number(params.get('page') ?? 1),
    };
}

export function visitSpatieQuery(query: Record<string, QueryValue>, options: VisitOptions = {}): void {
    const url = currentUrl(options.routeUrl);

    url.searchParams.delete('search');
    url.searchParams.delete('perPage');

    Object.entries(query).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            url.searchParams.delete(key);
            return;
        }

        url.searchParams.set(key, String(value));
    });

    if (options.resetPage !== false && !Object.hasOwn(query, 'page')) {
        url.searchParams.set('page', '1');
    }

    const visitOptions: Parameters<typeof router.visit>[1] = {
        replace: options.replace ?? true,
        preserveScroll: true,
        preserveState: true,
    };

    if (options.only && options.only.length > 0) {
        visitOptions.only = options.only;
    }

    router.visit(url.toString(), visitOptions);
}

export function setFilter(property: string, value: QueryValue, options: VisitOptions = {}): void {
    visitSpatieQuery(
        {
            [`filter[${property}]`]: value,
        },
        options,
    );
}

export function clearFilter(property: string, options: VisitOptions = {}): void {
    setFilter(property, null, options);
}
