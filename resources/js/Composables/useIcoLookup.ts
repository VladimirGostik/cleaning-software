import { onUnmounted } from 'vue';
 
import { ref } from 'vue';
import { icoLookupService } from '@/services';

export function useIcoLookup() {
    // eslint-disable-next-line no-restricted-syntax -- async state flag
    const loading = ref(false);
    // eslint-disable-next-line no-restricted-syntax -- async error state
    const error = ref<string | null>(null);
    // eslint-disable-next-line no-restricted-syntax -- async result state
    const data = ref<App.Data.Tenants.IcoLookupData | null>(null);

    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;

    function search(ico: string): void {
        if (timer !== null) {
            clearTimeout(timer);
        }
        if (!/^\d{8}$/.test(ico.trim())) {
            data.value = null;
            return;
        }
        timer = setTimeout(() => void run(ico.trim()), 400);
    }

    async function run(ico: string): Promise<void> {
        if (controller !== null) {
            controller.abort();
        }
        controller = new AbortController();
        loading.value = true;
        error.value = null;

        try {
            data.value = await icoLookupService.lookup(ico, controller.signal);
        } catch (err: unknown) {
            if (err instanceof Error && err.name === 'CanceledError') {
                return;
            }
            const isNotFound =
                typeof err === 'object' &&
                err !== null &&
                'response' in err &&
                (err as { response?: { status?: number } }).response?.status === 404;
            if (isNotFound) {
                data.value = null;
            } else {
                error.value = 'lookup_error';
            }
        } finally {
            loading.value = false;
        }
    }

    onUnmounted(() => {
        if (timer !== null) {
            clearTimeout(timer);
        }
        controller?.abort();
    });

    return { data, loading, error, search };
}
