import { computed, getCurrentScope, onScopeDispose, reactive } from 'vue';
import { getBlob, getJson } from '@/utils/http';
import { downloadBlob, mergePdfs } from '@/utils/pdfMerge';

export type BulkPdfPhase = 'idle' | 'collecting' | 'downloading' | 'merging' | 'error';
export type BulkPdfError = 'invoice_bulk_pdf_failed' | 'invoice_bulk_pdf_too_many';

function chunk<T>(items: T[], size: number): T[][] {
    const out: T[][] = [];
    for (let i = 0; i < items.length; i += size) out.push(items.slice(i, i + size));
    return out;
}

function listQuery(): string {
    const source = new URLSearchParams(window.location.search);
    const params = new URLSearchParams();
    source.forEach((value, key) => {
        if (key === 'sort' || /^filter\[.+]$/.test(key)) params.append(key, value);
    });
    return params.toString();
}

function filename(): string {
    const d = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    return `faktury-${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}.pdf`;
}

export function useInvoiceBulkPdf(limits: () => App.Data.Invoices.InvoiceBulkLimitsData) {
    const state = reactive({
        phase: 'idle' as BulkPdfPhase,
        done: 0,
        total: 0,
        error: null as BulkPdfError | null,
    });
    let controller: AbortController | null = null;

    const active = computed(() => ['collecting', 'downloading', 'merging'].includes(state.phase));

    function reset(): void {
        state.phase = 'idle';
        state.done = 0;
        state.total = 0;
        state.error = null;
    }

    function fail(error: BulkPdfError): void {
        state.phase = 'error';
        state.error = error;
    }

    async function run(ids: string[], signal: AbortSignal): Promise<void> {
        const groups = chunk(ids, limits().pdf_max_ids);
        state.phase = 'downloading';
        state.done = 0;
        state.total = groups.length;

        const parts: Blob[] = [];
        for (const group of groups) {
            const params = new URLSearchParams();
            group.forEach((id) => params.append('ids[]', id));
            parts.push(await getBlob(`/invoices/bulk/pdf?${params.toString()}`, { signal }));
            state.done += 1;
        }

        let merged = parts[0] as Blob;
        if (parts.length > 1) {
            state.phase = 'merging';
            merged = await mergePdfs(parts);
        }
        if (signal.aborted) return;
        downloadBlob(merged, filename());
        reset();
    }

    async function execute(resolveIds: (signal: AbortSignal) => Promise<string[] | null>): Promise<void> {
        if (active.value) return;
        controller = new AbortController();
        const { signal } = controller;
        reset();
        try {
            state.phase = 'collecting';
            const ids = await resolveIds(signal);
            if (ids === null) return fail('invoice_bulk_pdf_too_many');
            if (ids.length === 0) return reset();
            await run(ids, signal);
        } catch {
            if (controller?.signal === signal) {
                if (signal.aborted) reset();
                else fail('invoice_bulk_pdf_failed');
            }
        } finally {
            if (controller?.signal === signal) controller = null;
        }
    }

    const downloadIds = (ids: string[]) => execute(async () => ids);

    const downloadAllMatching = () =>
        execute(async (signal) => {
            const query = listQuery();
            const result = await getJson<App.Data.Invoices.InvoiceBulkIdsData>(
                `/invoices/bulk/ids${query ? `?${query}` : ''}`,
                { signal },
            );
            return result.truncated || result.ids.length > limits().print_all_max ? null : result.ids;
        });

    function cancel(): void {
        controller?.abort();
        controller = null;
        reset();
    }

    if (getCurrentScope()) onScopeDispose(() => controller?.abort());

    return { state, active, downloadIds, downloadAllMatching, cancel, dismiss: reset };
}
