import { getCurrentScope, onScopeDispose, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { getJson } from '@/utils/http';

const DEBOUNCE_MS = 300;
const ENDPOINT = '/invoices/payment-import/invoices';

export function useInvoiceOptionSearch() {
    const { t } = useI18n();
    const search = ref('');
    const results = ref<App.Data.Invoices.PaymentImportInvoiceData[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    let amount: string | null = null;
    let lastQuery: string | null = null;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;

    function cancel(): void {
        if (timer !== null) clearTimeout(timer);
        timer = null;
        controller?.abort();
        controller = null;
    }

    async function run(query: string): Promise<void> {
        controller?.abort();
        const current = new AbortController();
        controller = current;
        lastQuery = query;
        loading.value = true;
        error.value = null;

        const params = new URLSearchParams();
        if (query !== '') params.set('search', query);
        if (amount !== null) params.set('amount', amount);

        try {
            const response = await getJson<App.Data.Invoices.PaymentImportInvoiceData[]>(`${ENDPOINT}?${params}`, {
                signal: current.signal,
            });
            if (controller !== current) return;
            results.value = response;
        } catch {
            if (controller !== current) return;
            results.value = [];
            error.value = t('invoice_payment_import_assign_failed');
        } finally {
            if (controller === current) {
                loading.value = false;
                controller = null;
            }
        }
    }

    watch(search, (value) => {
        if (timer !== null) clearTimeout(timer);
        timer = setTimeout(() => {
            timer = null;
            if (value !== lastQuery) void run(value);
        }, DEBOUNCE_MS);
    });

    function start(forAmount: string | null): void {
        cancel();
        amount = forAmount;
        results.value = [];
        search.value = '';
        void run('');
    }

    function stop(): void {
        cancel();
        loading.value = false;
    }

    if (getCurrentScope()) onScopeDispose(stop);

    return { search, results, loading, error, start, stop };
}
