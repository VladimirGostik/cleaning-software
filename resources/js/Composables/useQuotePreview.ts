import { onUnmounted, ref } from 'vue';
import { HttpError, postBlob, postJson } from '@/utils/http';
import { quotePayload, type QuoteFormData } from '@/utils/quoteForm';
import { useDocumentPdfPreview } from '@/Composables/useDocumentPdfPreview';

interface PreviewForm {
    data(): QuoteFormData;
    setError(field: string, message: string): void;
    clearErrors(): void;
}

interface UseQuotePreviewOptions {
    form: PreviewForm;
    quoteId: string | null;
    incompleteMessage: () => string;
}

export type QuoteTextPreviewError = 'invalid' | 'throttled' | 'failed';

export function useQuotePreview(options: UseQuotePreviewOptions) {
    const base = options.quoteId === null ? '/quotes' : `/quotes/${options.quoteId}`;

    const textPreview = ref<App.Data.Quotes.QuoteTextPreviewData | null>(null);
    const textLoading = ref(false);
    const textError = ref<QuoteTextPreviewError | null>(null);

    let textController: AbortController | null = null;

    function applyErrors(error: HttpError): boolean {
        if (error.status !== 422 || error.errors === null) return false;

        options.form.clearErrors();
        Object.entries(error.errors).forEach(([field, messages]) => {
            const first = messages[0];
            if (first !== undefined) options.form.setError(field, first);
        });

        return true;
    }

    const pdf = useDocumentPdfPreview({
        load: async (signal) => {
            try {
                return await postBlob(`${base}/preview-pdf`, quotePayload(options.form.data()), { signal });
            } catch (error) {
                if (error instanceof HttpError && applyErrors(error)) {
                    throw new HttpError(422, null, options.incompleteMessage());
                }
                throw error;
            }
        },
    });

    async function loadTextPreview(): Promise<boolean> {
        textController?.abort();
        const controller = new AbortController();
        textController = controller;
        textLoading.value = true;
        textError.value = null;

        try {
            const result = await postJson<App.Data.Quotes.QuoteTextPreviewData>(
                `${base}/text-preview`,
                quotePayload(options.form.data()),
                { signal: controller.signal },
            );
            if (controller.signal.aborted) return false;
            textPreview.value = result;

            return true;
        } catch (error) {
            if (controller.signal.aborted || (error instanceof DOMException && error.name === 'AbortError')) {
                return false;
            }
            if (error instanceof HttpError && applyErrors(error)) {
                textError.value = 'invalid';
            } else {
                textError.value = error instanceof HttpError && error.status === 429 ? 'throttled' : 'failed';
            }

            return false;
        } finally {
            if (!controller.signal.aborted) textLoading.value = false;
        }
    }

    onUnmounted(() => textController?.abort());

    return { pdf, textPreview, textLoading, textError, loadTextPreview };
}
