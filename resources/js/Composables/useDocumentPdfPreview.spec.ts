import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { HttpError } from '@/utils/http';
import { useDocumentPdfPreview } from './useDocumentPdfPreview';

type Preview = ReturnType<typeof useDocumentPdfPreview>;

function mountPreview(load: (signal: AbortSignal) => Promise<Blob>): { preview: Preview; unmount: () => void } {
    let preview!: Preview;
    const app = createApp(
        defineComponent({
            setup() {
                preview = useDocumentPdfPreview({ load });
                return () => h('div');
            },
        }),
    );
    app.mount(document.createElement('div'));
    return { preview, unmount: () => app.unmount() };
}

const blob = (): Blob => new Blob(['%PDF'], { type: 'application/pdf' });

let counter = 0;
const createObjectURL = vi.fn(() => `blob:${++counter}`);
const revokeObjectURL = vi.fn();

beforeEach(() => {
    counter = 0;
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL, revokeObjectURL }));
});

afterEach(() => {
    createObjectURL.mockClear();
    revokeObjectURL.mockClear();
});

describe('useDocumentPdfPreview', () => {
    it('loads into pdfUrl', async () => {
        const { preview } = mountPreview(() => Promise.resolve(blob()));

        const pending = preview.open();
        expect(preview.loading.value).toBe(true);
        expect(preview.isOpen.value).toBe(true);
        await pending;

        expect(preview.loading.value).toBe(false);
        expect(preview.pdfUrl.value).toBe('blob:1');
    });

    it('reports a failure with the server message', async () => {
        const { preview } = mountPreview(() => Promise.reject(new HttpError(500, null, 'boom')));

        await preview.open();

        expect(preview.error.value).toEqual({ kind: 'failed', message: 'boom' });
        expect(preview.pdfUrl.value).toBeNull();
    });

    it('reports throttling on 429', async () => {
        const { preview } = mountPreview(() => Promise.reject(new HttpError(429)));

        await preview.open();

        expect(preview.error.value).toEqual({ kind: 'throttled', message: null });
    });

    it('revokes the url on close and on unmount', async () => {
        const { preview, unmount } = mountPreview(() => Promise.resolve(blob()));

        await preview.open();
        preview.close();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:1');
        expect(preview.isOpen.value).toBe(false);

        await preview.open();
        unmount();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:2');
    });

    it('aborts the earlier request and revokes the earlier url on reopen', async () => {
        const signals: AbortSignal[] = [];
        const { preview } = mountPreview((signal) => {
            signals.push(signal);
            return Promise.resolve(blob());
        });

        await preview.open();
        await preview.open();

        expect(signals[0].aborted).toBe(true);
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:1');
        expect(preview.pdfUrl.value).toBe('blob:2');
    });
});
