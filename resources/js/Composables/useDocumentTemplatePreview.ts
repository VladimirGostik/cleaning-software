import { onUnmounted, ref, watch, type Ref } from 'vue';
import { postJson } from '@/utils/http';

const DEBOUNCE_MS = 600;
const PREVIEW_URL = '/settings/document-templates/preview';

export function useDocumentTemplatePreview(options: {
    payload: () => App.Data.DocumentTemplates.DocumentTemplatePreviewData;
    enabled: Ref<boolean>;
}) {
    const preview = ref<App.Data.Documents.DocumentRenderData | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    let abortController: AbortController | null = null;
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    function cancel(): void {
        abortController?.abort();
        abortController = null;
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
    }

    async function load(): Promise<void> {
        const controller = new AbortController();
        abortController = controller;
        loading.value = true;
        error.value = null;

        try {
            const result = await postJson<App.Data.Documents.DocumentRenderData>(PREVIEW_URL, options.payload(), {
                signal: controller.signal,
            });
            if (controller.signal.aborted) return;
            preview.value = result;
        } catch (err) {
            if (err instanceof DOMException && err.name === 'AbortError') return;
            error.value = err instanceof Error ? err.message : 'preview failed';
        } finally {
            if (!controller.signal.aborted) loading.value = false;
        }
    }

    function schedule(delay: number): void {
        cancel();
        debounceTimer = setTimeout(() => {
            debounceTimer = null;
            void load();
        }, delay);
    }

    watch(options.payload, () => {
        if (options.enabled.value) schedule(DEBOUNCE_MS);
    });

    watch(
        options.enabled,
        (enabled) => {
            if (enabled) {
                schedule(0);
                return;
            }
            cancel();
            loading.value = false;
        },
        { immediate: true },
    );

    onUnmounted(cancel);

    return { preview, loading, error };
}
