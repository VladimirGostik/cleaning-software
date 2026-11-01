export class RequestError extends Error {
    constructor(
        readonly status: number,
        readonly serverMessage: string | null,
    ) {
        super(serverMessage ?? String(status));
    }
}

export function describeRequestError(e: unknown, t: (key: string) => string): string {
    if (!(e instanceof RequestError)) return t('expense_request_failed');
    if (e.status === 401 || e.status === 419) return t('session_expired_reload');
    if (e.status === 429) return t('request_throttled');

    return e.serverMessage ?? t('expense_request_failed');
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

interface ErrorBody {
    message?: string;
    errors?: Record<string, string[]>;
}

export async function postJson<T>(url: string, body: FormData | Record<string, unknown>): Promise<T> {
    const isForm = body instanceof FormData;
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(isForm ? {} : { 'Content-Type': 'application/json' }),
        },
        body: isForm ? body : JSON.stringify(body),
    });

    if (response.ok) {
        return (await response.json()) as T;
    }

    let payload: ErrorBody;
    try {
        payload = (await response.json()) as ErrorBody;
    } catch {
        payload = {};
    }

    const first = payload.errors ? Object.values(payload.errors)[0]?.[0] : undefined;

    throw new RequestError(response.status, first ?? payload.message ?? null);
}
