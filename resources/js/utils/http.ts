export class HttpError extends Error {
    constructor(
        public readonly status: number,
        public readonly retryAfterSeconds: number | null = null,
        public readonly serverMessage: string | null = null,
        public readonly errors: Record<string, string[]> | null = null,
    ) {
        super(`HTTP ${status}`);
        this.name = 'HttpError';
    }
}

function parseRetryAfter(header: string | null): number | null {
    if (header === null || header.trim() === '') {
        return null;
    }

    const seconds = Number(header);
    if (Number.isFinite(seconds) && seconds >= 0) {
        return Math.ceil(seconds);
    }

    const date = Date.parse(header);

    return Number.isNaN(date) ? null : Math.max(0, Math.ceil((date - Date.now()) / 1000));
}

function isJson(response: Response): boolean {
    return (response.headers.get('Content-Type') ?? '').includes('application/json');
}

function isStringListBag(value: unknown): value is Record<string, string[]> {
    return (
        typeof value === 'object' &&
        value !== null &&
        Object.values(value).every((list) => Array.isArray(list) && list.every((item) => typeof item === 'string'))
    );
}

async function failureOf(response: Response): Promise<HttpError> {
    const retryAfter = parseRetryAfter(response.headers.get('Retry-After'));
    if (!isJson(response)) {
        return new HttpError(response.status, retryAfter);
    }

    try {
        const body = (await response.json()) as { message?: unknown; errors?: unknown } | null;
        const message = typeof body?.message === 'string' && body.message !== '' ? body.message : null;
        const errors = response.status === 422 && isStringListBag(body?.errors) ? body.errors : null;

        return new HttpError(response.status, retryAfter, message, errors);
    } catch {
        return new HttpError(response.status, retryAfter);
    }
}

export function getXsrfToken(): string {
    const entry = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    return entry === undefined ? '' : decodeURIComponent(entry.slice('XSRF-TOKEN='.length));
}

async function send(url: string, body: unknown, accept: string, signal?: AbortSignal): Promise<Response> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-XSRF-TOKEN': getXsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: accept,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
        signal,
    });

    if (!response.ok) {
        throw await failureOf(response);
    }

    return response;
}

export async function getJson<T>(url: string, opts?: { signal?: AbortSignal }): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: opts?.signal,
    });

    if (!response.ok) {
        throw await failureOf(response);
    }

    return (await response.json()) as T;
}

export async function getBlob(url: string, opts?: { signal?: AbortSignal }): Promise<Blob> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json, application/pdf;q=0.9', 'X-Requested-With': 'XMLHttpRequest' },
        signal: opts?.signal,
    });

    if (!response.ok) {
        throw await failureOf(response);
    }

    if (!(response.headers.get('Content-Type') ?? '').includes('application/pdf')) {
        throw new HttpError(response.status);
    }

    return await response.blob();
}

export async function postJson<T>(url: string, body: unknown, opts?: { signal?: AbortSignal }): Promise<T> {
    const response = await send(url, body, 'application/json', opts?.signal);

    return (await response.json()) as T;
}

export async function postBlob(url: string, body: unknown, opts?: { signal?: AbortSignal }): Promise<Blob> {
    const response = await send(url, body, 'application/json, application/pdf;q=0.9', opts?.signal);

    if (!(response.headers.get('Content-Type') ?? '').includes('application/pdf')) {
        throw new HttpError(response.status);
    }

    return await response.blob();
}
