import { effectScope } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { HttpError } from '@/utils/http';
import { useInvoiceBulkPdf } from './useInvoiceBulkPdf';

const mocks = vi.hoisted(() => ({
    getBlob: vi.fn(),
    getJson: vi.fn(),
    mergePdfs: vi.fn(),
    downloadBlob: vi.fn(),
}));

vi.mock('@/utils/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/http')>()),
    getBlob: mocks.getBlob,
    getJson: mocks.getJson,
}));
vi.mock('@/utils/pdfMerge', () => ({ mergePdfs: mocks.mergePdfs, downloadBlob: mocks.downloadBlob }));

const limits = { max_ids: 100, pdf_max_ids: 25, print_all_max: 200 } as App.Data.Invoices.InvoiceBulkLimitsData;
const ids = (n: number) => Array.from({ length: n }, (_, i) => `id-${i}`);

describe('useInvoiceBulkPdf', () => {
    beforeEach(() => {
        Object.values(mocks).forEach((m) => m.mockReset());
        mocks.getBlob.mockResolvedValue(new Blob(['x'], { type: 'application/pdf' }));
        mocks.mergePdfs.mockResolvedValue(new Blob(['m'], { type: 'application/pdf' }));
    });

    it('splits 60 ids into ordered chunks of 25/25/10 and merges once', async () => {
        const bulk = useInvoiceBulkPdf(() => limits);
        await bulk.downloadIds(ids(60));

        expect(mocks.getBlob).toHaveBeenCalledTimes(3);
        const counts = mocks.getBlob.mock.calls.map(([url]) => (url as string).split('ids%5B%5D=').length - 1);
        expect(counts).toEqual([25, 25, 10]);
        expect(decodeURIComponent(mocks.getBlob.mock.calls[0]![0] as string)).toContain('ids[]=id-0&ids[]=id-1');
        expect(decodeURIComponent(mocks.getBlob.mock.calls[2]![0] as string)).toContain('ids[]=id-50');
        expect(mocks.mergePdfs).toHaveBeenCalledTimes(1);
        expect(mocks.mergePdfs.mock.calls[0]![0]).toHaveLength(3);
        expect(mocks.downloadBlob).toHaveBeenCalledTimes(1);
        expect(mocks.downloadBlob.mock.calls[0]![1]).toMatch(/^faktury-\d{4}-\d{2}-\d{2}\.pdf$/);
        expect(bulk.state.phase).toBe('idle');
    });

    it('errors and downloads nothing when a chunk is not a PDF', async () => {
        mocks.getBlob.mockResolvedValueOnce(new Blob(['x'])).mockRejectedValueOnce(new HttpError(200));
        const bulk = useInvoiceBulkPdf(() => limits);
        await bulk.downloadIds(ids(60));

        expect(bulk.state.phase).toBe('error');
        expect(bulk.state.error).toBe('invoice_bulk_pdf_failed');
        expect(mocks.getBlob).toHaveBeenCalledTimes(2);
        expect(mocks.mergePdfs).not.toHaveBeenCalled();
        expect(mocks.downloadBlob).not.toHaveBeenCalled();
    });

    it('cancel stops the remaining chunks and downloads nothing', async () => {
        let bulk!: ReturnType<typeof useInvoiceBulkPdf>;
        mocks.getBlob.mockImplementation(async (_url: string, opts: { signal: AbortSignal }) => {
            bulk.cancel();
            if (opts.signal.aborted) throw new DOMException('aborted', 'AbortError');
            return new Blob(['x'], { type: 'application/pdf' });
        });
        bulk = useInvoiceBulkPdf(() => limits);
        await bulk.downloadIds(ids(60));

        expect(mocks.getBlob).toHaveBeenCalledTimes(1);
        expect(mocks.mergePdfs).not.toHaveBeenCalled();
        expect(mocks.downloadBlob).not.toHaveBeenCalled();
        expect(bulk.state.phase).toBe('idle');
    });

    it('downloadAllMatching forwards filter and sort and refuses a truncated result', async () => {
        window.history.replaceState({}, '', '/invoices?filter[status]=issued&sort=-number&page=3');
        mocks.getJson.mockResolvedValue({ ids: ids(3), total: 300, truncated: true });
        const bulk = useInvoiceBulkPdf(() => limits);
        await bulk.downloadAllMatching();

        const url = decodeURIComponent(mocks.getJson.mock.calls[0]![0] as string);
        expect(url).toBe('/invoices/bulk/ids?filter[status]=issued&sort=-number');
        expect(bulk.state.error).toBe('invoice_bulk_pdf_too_many');
        expect(mocks.getBlob).not.toHaveBeenCalled();
    });

    it('downloadAllMatching downloads the resolved ids when not truncated', async () => {
        window.history.replaceState({}, '', '/invoices?filter[status]=issued&page=2&per_page=10');
        mocks.getJson.mockResolvedValue({ ids: ids(30), total: 30, truncated: false });
        const bulk = useInvoiceBulkPdf(() => limits);
        await bulk.downloadAllMatching();

        expect(decodeURIComponent(mocks.getJson.mock.calls[0]![0] as string)).toBe(
            '/invoices/bulk/ids?filter[status]=issued',
        );
        expect(mocks.getBlob).toHaveBeenCalledTimes(2);
        expect(mocks.downloadBlob).toHaveBeenCalledTimes(1);
    });

    it('a single chunk is downloaded as is, without the merge step', async () => {
        const part = new Blob(['p'], { type: 'application/pdf' });
        mocks.getBlob.mockResolvedValue(part);
        const bulk = useInvoiceBulkPdf(() => limits);
        await bulk.downloadIds(ids(10));

        expect(mocks.getBlob).toHaveBeenCalledTimes(1);
        expect(mocks.mergePdfs).not.toHaveBeenCalled();
        expect(mocks.downloadBlob).toHaveBeenCalledTimes(1);
        expect(mocks.downloadBlob.mock.calls[0]![0]).toBe(part);
    });

    it('a cancelled run that is still merging does not touch the next run', async () => {
        let releaseMerge!: () => void;
        mocks.mergePdfs.mockImplementationOnce(
            () => new Promise((resolve) => (releaseMerge = () => resolve(new Blob(['m'])))),
        );
        const bulk = useInvoiceBulkPdf(() => limits);
        const first = bulk.downloadIds(ids(60));
        await vi.waitFor(() => expect(bulk.state.phase).toBe('merging'));
        bulk.cancel();

        let releaseBlob!: () => void;
        mocks.getBlob.mockImplementationOnce(
            () => new Promise((resolve) => (releaseBlob = () => resolve(new Blob(['x'], { type: 'application/pdf' })))),
        );
        const second = bulk.downloadIds(ids(5));
        await vi.waitFor(() => expect(bulk.state.phase).toBe('downloading'));

        releaseMerge();
        await first;
        expect(bulk.state.phase).toBe('downloading');
        expect(mocks.downloadBlob).not.toHaveBeenCalled();

        bulk.cancel();
        releaseBlob();
        await second;
        expect(bulk.state.phase).toBe('idle');
    });

    it('stops the chain and saves nothing when the owning scope is disposed', async () => {
        const scope = effectScope();
        const bulk = scope.run(() => useInvoiceBulkPdf(() => limits))!;
        mocks.getBlob.mockImplementation(async (_url: string, opts: { signal: AbortSignal }) => {
            scope.stop();
            if (opts.signal.aborted) throw new DOMException('aborted', 'AbortError');
            return new Blob(['x'], { type: 'application/pdf' });
        });
        await bulk.downloadIds(ids(60));

        expect(mocks.getBlob).toHaveBeenCalledTimes(1);
        expect(mocks.mergePdfs).not.toHaveBeenCalled();
        expect(mocks.downloadBlob).not.toHaveBeenCalled();
    });

    it('reports a failed ids lookup and an empty result without downloading', async () => {
        mocks.getJson.mockRejectedValueOnce(new HttpError(403));
        const bulk = useInvoiceBulkPdf(() => limits);
        await bulk.downloadAllMatching();

        expect(bulk.state.error).toBe('invoice_bulk_pdf_failed');

        await bulk.downloadIds([]);

        expect(bulk.state.phase).toBe('idle');
        expect(mocks.getBlob).not.toHaveBeenCalled();
    });
});
