import { onUnmounted, ref, watch } from 'vue';
import { getJson } from '@/utils/http';

const DEBOUNCE_MS = 250;

export interface BuyerPreviewParams {
    client_id: string;
    cleaning_object_id: string | null;
}

export function useBuyerPreview(params: () => BuyerPreviewParams | null) {
    const preview = ref<App.Data.Invoices.InvoiceBuyerPreviewResultData | null>(null);
    const loading = ref(false);
    const error = ref(false);

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

    async function load(current: BuyerPreviewParams): Promise<void> {
        const controller = new AbortController();
        abortController = controller;
        loading.value = true;
        error.value = false;

        const query = new URLSearchParams({
            client_id: current.client_id,
            cleaning_object_id: current.cleaning_object_id ?? '',
        });

        try {
            const result = await getJson<App.Data.Invoices.InvoiceBuyerPreviewResultData>(
                `/invoices/buyer-preview?${query.toString()}`,
                { signal: controller.signal },
            );
            if (controller.signal.aborted) return;
            preview.value = result;
        } catch (err) {
            if (controller.signal.aborted || (err instanceof DOMException && err.name === 'AbortError')) return;
            error.value = true;
        } finally {
            if (!controller.signal.aborted) loading.value = false;
        }
    }

    watch(
        () => {
            const current = params();
            return current === null ? null : `${current.client_id}|${current.cleaning_object_id ?? ''}`;
        },
        (key) => {
            cancel();
            preview.value = null;
            const current = params();
            if (key === null || current === null) {
                loading.value = false;
                error.value = false;
                return;
            }
            debounceTimer = setTimeout(() => {
                debounceTimer = null;
                void load(current);
            }, DEBOUNCE_MS);
        },
        { immediate: true },
    );

    onUnmounted(cancel);

    return { preview, loading, error };
}
