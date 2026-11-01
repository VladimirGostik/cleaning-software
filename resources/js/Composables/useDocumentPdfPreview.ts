import { onUnmounted, ref } from 'vue';
import { HttpError, postBlob } from '@/utils/http';

export interface PdfPreviewError {
    kind: 'throttled' | 'failed';
    message: string | null;
}

export function useDocumentPdfPreview(options: { url: string; payload: () => unknown }) {
    const pdfUrl = ref<string | null>(null);
    const loading = ref(false);
    const error = ref<PdfPreviewError | null>(null);
    const isOpen = ref(false);

    let abortController: AbortController | null = null;

    function release(): void {
        abortController?.abort();
        abortController = null;
        if (pdfUrl.value !== null) {
            URL.revokeObjectURL(pdfUrl.value);
            pdfUrl.value = null;
        }
    }

    async function open(): Promise<void> {
        release();
        const controller = new AbortController();
        abortController = controller;
        isOpen.value = true;
        loading.value = true;
        error.value = null;

        try {
            const blob = await postBlob(options.url, options.payload(), { signal: controller.signal });
            if (controller.signal.aborted) return;
            pdfUrl.value = URL.createObjectURL(blob);
        } catch (err) {
            if (controller.signal.aborted || (err instanceof DOMException && err.name === 'AbortError')) return;
            error.value =
                err instanceof HttpError && err.status === 429
                    ? { kind: 'throttled', message: null }
                    : { kind: 'failed', message: err instanceof HttpError ? err.serverMessage : null };
        } finally {
            if (!controller.signal.aborted) loading.value = false;
        }
    }

    function close(): void {
        release();
        isOpen.value = false;
        loading.value = false;
        error.value = null;
    }

    onUnmounted(release);

    return { pdfUrl, loading, error, isOpen, open, close };
}
