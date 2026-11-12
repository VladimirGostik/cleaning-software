import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { HttpError } from '@/utils/http';
import type { QuoteFormData } from '@/utils/quoteForm';
import { useQuotePreview } from './useQuotePreview';

const http = vi.hoisted(() => ({ postBlob: vi.fn(), postJson: vi.fn() }));

vi.mock('@/utils/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/http')>()),
    postBlob: http.postBlob,
    postJson: http.postJson,
}));

const formData = { kind: 'itemized', items: [], subject: '', number: '', note: '', included_text: '' };

function mountPreview(quoteId: string | null) {
    const form = {
        data: () => formData as unknown as QuoteFormData,
        setError: vi.fn(),
        clearErrors: vi.fn(),
    };
    let preview!: ReturnType<typeof useQuotePreview>;
    const app = createApp(
        defineComponent({
            setup() {
                preview = useQuotePreview({ form, quoteId, incompleteMessage: () => 'incomplete' });
                return () => h('div');
            },
        }),
    );
    app.mount(document.createElement('div'));

    return { preview, form, unmount: () => app.unmount() };
}

beforeEach(() => {
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: () => 'blob:1', revokeObjectURL: vi.fn() }));
});

afterEach(() => {
    http.postBlob.mockReset();
    http.postJson.mockReset();
});

describe('useQuotePreview', () => {
    it('posts the form payload to the unsaved or saved quote route', async () => {
        http.postBlob.mockResolvedValue(new Blob(['%PDF'], { type: 'application/pdf' }));

        const created = mountPreview(null);
        await created.preview.pdf.open();
        const saved = mountPreview('q-1');
        await saved.preview.pdf.open();

        expect(http.postBlob.mock.calls[0]?.[0]).toBe('/quotes/preview-pdf');
        expect(http.postBlob.mock.calls[1]?.[0]).toBe('/quotes/q-1/preview-pdf');
        expect(created.preview.pdf.pdfUrl.value).toBe('blob:1');
    });

    it('maps a 422 of the pdf onto the form and shows the incomplete message', async () => {
        http.postBlob.mockRejectedValue(new HttpError(422, null, null, { customer_name: ['required'] }));
        const { preview, form } = mountPreview(null);

        await preview.pdf.open();

        expect(form.setError).toHaveBeenCalledWith('customer_name', 'required');
        expect(preview.pdf.error.value).toEqual({ kind: 'failed', message: 'incomplete' });
    });

    it('stores the text preview and reports success', async () => {
        const data = { values: { 'quote.subject': 'X' }, blocks: {}, missing_tokens: [] };
        http.postJson.mockResolvedValue(data);
        const { preview } = mountPreview('q-1');

        await expect(preview.loadTextPreview()).resolves.toBe(true);

        expect(http.postJson.mock.calls[0]?.[0]).toBe('/quotes/q-1/text-preview');
        expect(preview.textPreview.value).toEqual(data);
        expect(preview.textLoading.value).toBe(false);
    });

    it('maps a 422 and a 429 of the text preview', async () => {
        const { preview, form } = mountPreview(null);

        http.postJson.mockRejectedValueOnce(new HttpError(422, null, null, { items: ['required'] }));
        await expect(preview.loadTextPreview()).resolves.toBe(false);
        expect(preview.textError.value).toBe('invalid');
        expect(form.setError).toHaveBeenCalledWith('items', 'required');

        http.postJson.mockRejectedValueOnce(new HttpError(429));
        await preview.loadTextPreview();
        expect(preview.textError.value).toBe('throttled');
    });
});
