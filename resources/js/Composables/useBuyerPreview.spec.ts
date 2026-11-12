import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h, nextTick, ref } from 'vue';
import { HttpError } from '@/utils/http';
import { useBuyerPreview, type BuyerPreviewParams } from './useBuyerPreview';

const http = vi.hoisted(() => ({ getJson: vi.fn() }));

vi.mock('@/utils/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/http')>()),
    getJson: http.getJson,
}));

function mountPreview(source: { value: BuyerPreviewParams | null }) {
    let result!: ReturnType<typeof useBuyerPreview>;
    const app = createApp(
        defineComponent({
            setup() {
                result = useBuyerPreview(() => source.value, '/invoices/buyer-preview');
                return () => h('div');
            },
        }),
    );
    app.mount(document.createElement('div'));
    return { result, app };
}

const resultData = (email: string): App.Data.Invoices.InvoiceBuyerPreviewResultData =>
    ({
        buyer: { source: 'client', name: 'A' },
        email_options: [email],
    }) as App.Data.Invoices.InvoiceBuyerPreviewResultData;

describe('useBuyerPreview', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        http.getJson.mockReset();
    });
    afterEach(() => vi.useRealTimers());

    it('sends no request for null params', async () => {
        const source = ref<BuyerPreviewParams | null>(null);
        const { result } = mountPreview(source);

        await vi.advanceTimersByTimeAsync(1000);

        expect(http.getJson).not.toHaveBeenCalled();
        expect(result.preview.value).toBeNull();
    });

    it('requests once after the debounce with the right query', async () => {
        http.getJson.mockResolvedValue(resultData('a@b.sk'));
        const source = ref<BuyerPreviewParams | null>({ client_id: 'c1', cleaning_object_id: null });
        const { result } = mountPreview(source);

        await vi.advanceTimersByTimeAsync(100);
        expect(http.getJson).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(300);

        expect(http.getJson).toHaveBeenCalledTimes(1);
        expect(http.getJson.mock.calls[0][0]).toBe('/invoices/buyer-preview?client_id=c1&cleaning_object_id=');
        expect(result.preview.value?.email_options).toEqual(['a@b.sk']);
        expect(result.loading.value).toBe(false);
    });

    it('requests the given base path', async () => {
        http.getJson.mockResolvedValue(resultData('a@b.sk'));
        const source = ref<BuyerPreviewParams | null>({ client_id: 'c1', cleaning_object_id: 'o1' });
        let result!: ReturnType<typeof useBuyerPreview>;
        createApp(
            defineComponent({
                setup() {
                    result = useBuyerPreview(() => source.value, '/quotes/buyer-preview');
                    return () => h('div');
                },
            }),
        ).mount(document.createElement('div'));

        await vi.advanceTimersByTimeAsync(300);

        expect(http.getJson.mock.calls[0][0]).toBe('/quotes/buyer-preview?client_id=c1&cleaning_object_id=o1');
        expect(result.preview.value).not.toBeNull();
    });

    it('aborts a superseded request', async () => {
        let firstSignal: AbortSignal | undefined;
        http.getJson.mockImplementationOnce((_url: string, opts: { signal: AbortSignal }) => {
            firstSignal = opts.signal;
            return new Promise(() => undefined);
        });
        http.getJson.mockResolvedValueOnce(resultData('b@b.sk'));
        const source = ref<BuyerPreviewParams | null>({ client_id: 'c1', cleaning_object_id: null });
        const { result } = mountPreview(source);

        await vi.advanceTimersByTimeAsync(300);
        source.value = { client_id: 'c1', cleaning_object_id: 'o1' };
        await nextTick();
        await vi.advanceTimersByTimeAsync(300);

        expect(firstSignal?.aborted).toBe(true);
        expect(http.getJson).toHaveBeenCalledTimes(2);
        expect(result.preview.value?.email_options).toEqual(['b@b.sk']);
    });

    it('clears the previous preview as soon as the params change', async () => {
        http.getJson.mockResolvedValueOnce(resultData('a@b.sk'));
        http.getJson.mockReturnValueOnce(new Promise(() => undefined));
        const source = ref<BuyerPreviewParams | null>({ client_id: 'c1', cleaning_object_id: null });
        const { result } = mountPreview(source);

        await vi.advanceTimersByTimeAsync(300);
        expect(result.preview.value?.email_options).toEqual(['a@b.sk']);

        source.value = { client_id: 'c2', cleaning_object_id: null };
        await nextTick();

        expect(result.preview.value).toBeNull();
        await vi.advanceTimersByTimeAsync(300);
        expect(result.preview.value).toBeNull();
    });

    it('sets error on HttpError', async () => {
        http.getJson.mockRejectedValue(new HttpError(500));
        const source = ref<BuyerPreviewParams | null>({ client_id: 'c1', cleaning_object_id: null });
        const { result } = mountPreview(source);

        await vi.advanceTimersByTimeAsync(300);

        expect(result.error.value).toBe(true);
        expect(result.loading.value).toBe(false);
    });
});
