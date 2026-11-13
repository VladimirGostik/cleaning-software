import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { HttpError, deleteJson, getBlob, getJson, getXsrfToken, patchJson, postBlob, postForm, postJson } from './http';

const fetchMock = vi.fn<typeof fetch>();

function response(body: string | null, status: number, headers: Record<string, string>): Response {
    return new Response(body, { status, headers });
}

beforeEach(() => {
    vi.stubGlobal('fetch', fetchMock);
    document.cookie = 'XSRF-TOKEN=abc%3D%3D; path=/';
});

afterEach(() => {
    fetchMock.mockReset();
    vi.unstubAllGlobals();
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
});

describe('getXsrfToken', () => {
    it('reads and decodes the XSRF-TOKEN cookie', () => {
        expect(getXsrfToken()).toBe('abc==');
    });

    it('returns an empty string without the cookie', () => {
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';

        expect(getXsrfToken()).toBe('');
    });
});

describe('getJson', () => {
    it('requests JSON without a body and returns the payload', async () => {
        fetchMock.mockResolvedValue(response('{"ok":true}', 200, { 'Content-Type': 'application/json' }));

        await expect(getJson('/x')).resolves.toEqual({ ok: true });

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/x');
        expect(init?.method).toBeUndefined();
        expect(init?.headers).toMatchObject({ Accept: 'application/json' });
    });

    it('throws HttpError with the status', async () => {
        fetchMock.mockResolvedValue(response(null, 404, {}));

        await expect(getJson('/x')).rejects.toMatchObject({ status: 404 });
    });
});

describe('postForm', () => {
    it('sends the FormData as is with the XSRF header and no Content-Type', async () => {
        fetchMock.mockResolvedValue(response('{"ok":true}', 200, { 'Content-Type': 'application/json' }));
        const body = new FormData();
        body.append('a', '1');

        await expect(postForm('/x', body)).resolves.toEqual({ ok: true });

        const [, init] = fetchMock.mock.calls[0];
        expect(init?.body).toBe(body);
        expect(init?.headers).toMatchObject({ 'X-XSRF-TOKEN': 'abc==', Accept: 'application/json' });
        expect(init?.headers).not.toHaveProperty('Content-Type');
    });

    it('maps a 413 to HttpError', async () => {
        fetchMock.mockResolvedValue(response(null, 413, {}));

        await expect(postForm('/x', new FormData())).rejects.toMatchObject({ status: 413 });
    });
});

describe('postJson', () => {
    it('sends the XSRF header and JSON body and returns the parsed payload', async () => {
        fetchMock.mockResolvedValue(response('{"ok":true}', 200, { 'Content-Type': 'application/json' }));

        await expect(postJson('/x', { a: 1 })).resolves.toEqual({ ok: true });

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/x');
        expect(init?.method).toBe('POST');
        expect(init?.body).toBe('{"a":1}');
        expect(init?.headers).toMatchObject({ 'X-XSRF-TOKEN': 'abc==', Accept: 'application/json' });
    });

    it('maps a 422 body to message and errors', async () => {
        fetchMock.mockResolvedValue(
            response('{"message":"Bad","errors":{"body":["Too long"]}}', 422, { 'Content-Type': 'application/json' }),
        );

        const error = await postJson('/x', {}).catch((e: unknown) => e);

        expect(error).toBeInstanceOf(HttpError);
        expect(error).toMatchObject({ status: 422, serverMessage: 'Bad', errors: { body: ['Too long'] } });
    });

    it('exposes Retry-After on 429 and ignores a malformed errors bag', async () => {
        fetchMock.mockResolvedValueOnce(response(null, 429, { 'Retry-After': '30' }));
        fetchMock.mockResolvedValueOnce(
            response('{"errors":{"body":"x"}}', 422, { 'Content-Type': 'application/json' }),
        );

        await expect(postJson('/x', {})).rejects.toMatchObject({ status: 429, retryAfterSeconds: 30 });
        await expect(postJson('/x', {})).rejects.toMatchObject({ status: 422, errors: null });
    });

    it('keeps errors null for non-422 json bodies', async () => {
        fetchMock.mockResolvedValue(
            response('{"message":"Nope","errors":{"a":["b"]}}', 403, { 'Content-Type': 'application/json' }),
        );

        await expect(postJson('/x', {})).rejects.toMatchObject({ status: 403, serverMessage: 'Nope', errors: null });
    });
});

describe('patchJson', () => {
    it('sends PATCH with the XSRF header and JSON body', async () => {
        fetchMock.mockResolvedValue(response('{"ok":true}', 200, { 'Content-Type': 'application/json' }));

        await expect(patchJson('/x', { title: 'a' })).resolves.toEqual({ ok: true });

        const [, init] = fetchMock.mock.calls[0];
        expect(init?.method).toBe('PATCH');
        expect(init?.body).toBe('{"title":"a"}');
        expect(init?.headers).toMatchObject({ 'X-XSRF-TOKEN': 'abc==', 'Content-Type': 'application/json' });
    });

    it('maps a 422 to HttpError with errors', async () => {
        fetchMock.mockResolvedValue(
            response('{"errors":{"title":["Required"]}}', 422, { 'Content-Type': 'application/json' }),
        );

        await expect(patchJson('/x', {})).rejects.toMatchObject({ status: 422, errors: { title: ['Required'] } });
    });
});

describe('deleteJson', () => {
    it('sends DELETE with the XSRF header and resolves on 204', async () => {
        fetchMock.mockResolvedValue(new Response(null, { status: 204 }));

        await expect(deleteJson('/x')).resolves.toBeUndefined();

        const [, init] = fetchMock.mock.calls[0];
        expect(init?.method).toBe('DELETE');
        expect(init?.body).toBeUndefined();
        expect(init?.headers).toMatchObject({ 'X-XSRF-TOKEN': 'abc==', Accept: 'application/json' });
    });

    it('throws HttpError for 404', async () => {
        fetchMock.mockResolvedValue(response(null, 404, {}));

        await expect(deleteJson('/x')).rejects.toMatchObject({ status: 404 });
    });
});

describe('postBlob', () => {
    it('asks json first and returns the pdf blob', async () => {
        fetchMock.mockResolvedValue(response('%PDF', 200, { 'Content-Type': 'application/pdf' }));

        const blob = await postBlob('/pdf', { body: 'x' });

        expect(blob.size).toBe(4);
        expect(fetchMock.mock.calls[0][1]?.headers).toMatchObject({
            Accept: 'application/json, application/pdf;q=0.9',
        });
    });

    it('throws HttpError for a non-pdf 200 and for non-2xx', async () => {
        fetchMock.mockResolvedValueOnce(response('<html>', 200, { 'Content-Type': 'text/html' }));
        fetchMock.mockResolvedValueOnce(response('{"message":"x"}', 500, { 'Content-Type': 'application/json' }));

        await expect(postBlob('/pdf', {})).rejects.toBeInstanceOf(HttpError);
        await expect(postBlob('/pdf', {})).rejects.toMatchObject({ status: 500, serverMessage: 'x' });
    });
});

describe('getBlob', () => {
    it('returns the pdf blob', async () => {
        fetchMock.mockResolvedValue(response('%PDF', 200, { 'Content-Type': 'application/pdf' }));

        const blob = await getBlob('/pdf');

        expect(blob.size).toBe(4);
        expect(fetchMock.mock.calls[0][1]?.headers).toMatchObject({
            Accept: 'application/json, application/pdf;q=0.9',
        });
    });

    it('throws HttpError with the server message on a json failure', async () => {
        fetchMock.mockResolvedValue(response('{"message":"boom"}', 500, { 'Content-Type': 'application/json' }));

        await expect(getBlob('/pdf')).rejects.toMatchObject({ status: 500, serverMessage: 'boom' });
    });

    it('keeps status 429', async () => {
        fetchMock.mockResolvedValue(response(null, 429, { 'Retry-After': '5' }));

        await expect(getBlob('/pdf')).rejects.toMatchObject({ status: 429, retryAfterSeconds: 5 });
    });

    it('throws for a non-pdf 200', async () => {
        fetchMock.mockResolvedValue(response('<html>', 200, { 'Content-Type': 'text/html' }));

        await expect(getBlob('/pdf')).rejects.toBeInstanceOf(HttpError);
    });
});
