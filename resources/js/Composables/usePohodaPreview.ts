import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue';

const PREVIEW_URL = '/invoices/export/pohoda/preview';
const DEBOUNCE_MS = 300;

interface ValidationBody {
    errors?: Partial<Record<'from' | 'to', string[]>>;
}

export function usePohodaPreview(from: Ref<string | null>, to: Ref<string | null>, active: Ref<boolean>) {
    const preview = ref<App.Data.Invoices.PohodaExportPreviewData | null>(null);
    const loading = ref(false);
    const failed = ref(false);
    const fieldErrors = ref<{ from: string | null; to: string | null }>({ from: null, to: null });

    let timer: number | null = null;
    let controller: AbortController | null = null;

    function cancel(): void {
        if (timer !== null) window.clearTimeout(timer);
        timer = null;
        controller?.abort();
        controller = null;
    }

    async function load(fromValue: string, toValue: string): Promise<void> {
        const own = new AbortController();
        controller = own;

        try {
            const query = new URLSearchParams({ from: fromValue, to: toValue });
            const response = await fetch(`${PREVIEW_URL}?${query.toString()}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: own.signal,
            });

            if (own.signal.aborted) return;

            if (response.status === 422) {
                const body = (await response.json()) as ValidationBody;
                if (own.signal.aborted) return;
                fieldErrors.value = { from: body.errors?.from?.[0] ?? null, to: body.errors?.to?.[0] ?? null };
                loading.value = false;
                return;
            }

            if (!response.ok) {
                failed.value = true;
                loading.value = false;
                return;
            }

            const data = (await response.json()) as App.Data.Invoices.PohodaExportPreviewData;
            if (own.signal.aborted) return;
            preview.value = data;
            loading.value = false;
        } catch {
            if (own.signal.aborted) return;
            failed.value = true;
            loading.value = false;
        }
    }

    function schedule(): void {
        cancel();
        preview.value = null;
        failed.value = false;
        fieldErrors.value = { from: null, to: null };

        if (!active.value || !from.value || !to.value) {
            loading.value = false;
            return;
        }

        loading.value = true;
        const fromValue = from.value;
        const toValue = to.value;
        timer = window.setTimeout(() => void load(fromValue, toValue), DEBOUNCE_MS);
    }

    watch([from, to, active], schedule, { immediate: true });
    onBeforeUnmount(cancel);

    const canDownload = computed(() => !loading.value && !failed.value && (preview.value?.count ?? 0) > 0);

    return { preview, loading, failed, fieldErrors, canDownload, retry: schedule };
}
