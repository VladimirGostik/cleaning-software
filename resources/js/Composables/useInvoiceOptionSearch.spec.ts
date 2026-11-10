import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { useInvoiceOptionSearch } from './useInvoiceOptionSearch';

const getJson = vi.fn();

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@/utils/http', () => ({ getJson: (...args: unknown[]) => getJson(...args) }));

function invoice(id: string): App.Data.Invoices.PaymentImportInvoiceData {
    return {
        id,
        number: id,
        customer_name: null,
        status: 'issued',
        issue_date: '2026-10-01',
        due_date: '2026-10-15',
        total: '10.00',
        balance_due: '10.00',
    };
}

describe('useInvoiceOptionSearch', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        getJson.mockReset();
    });
    afterEach(() => vi.useRealTimers());

    it('start queries at once with the amount and no duplicate debounced query', async () => {
        getJson.mockResolvedValue([invoice('a')]);
        const s = useInvoiceOptionSearch();

        s.start('12.50');
        await nextTick();
        await vi.advanceTimersByTimeAsync(400);

        expect(getJson).toHaveBeenCalledTimes(1);
        expect(getJson.mock.calls[0][0]).toBe('/invoices/payment-import/invoices?amount=12.50');
        expect(s.results.value.map((i) => i.id)).toEqual(['a']);
        expect(s.loading.value).toBe(false);
    });

    it('debounces typing and sends search and amount', async () => {
        getJson.mockResolvedValue([]);
        const s = useInvoiceOptionSearch();
        s.start('5.00');
        await vi.advanceTimersByTimeAsync(0);
        getJson.mockClear();

        s.search.value = 'fa';
        await nextTick();
        s.search.value = 'fa 1';
        await nextTick();
        await vi.advanceTimersByTimeAsync(299);
        expect(getJson).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(2);

        expect(getJson).toHaveBeenCalledTimes(1);
        expect(getJson.mock.calls[0][0]).toBe('/invoices/payment-import/invoices?search=fa+1&amount=5.00');
        expect(s.results.value).toEqual([]);
    });

    it('aborts the old request and ignores its late response', async () => {
        let resolveFirst: (v: unknown) => void = () => undefined;
        getJson.mockImplementationOnce(() => new Promise((resolve) => (resolveFirst = resolve)));
        getJson.mockResolvedValueOnce([invoice('new')]);
        const s = useInvoiceOptionSearch();

        s.start(null);
        const firstSignal = getJson.mock.calls[0][1].signal as AbortSignal;
        s.start(null);
        await vi.advanceTimersByTimeAsync(0);
        resolveFirst([invoice('old')]);
        await vi.advanceTimersByTimeAsync(0);

        expect(firstSignal.aborted).toBe(true);
        expect(s.results.value.map((i) => i.id)).toEqual(['new']);
    });

    it('fills error on failure and stop aborts', async () => {
        getJson.mockRejectedValueOnce(new Error('x'));
        const s = useInvoiceOptionSearch();
        s.start(null);
        await vi.advanceTimersByTimeAsync(0);

        expect(s.error.value).toBe('invoice_payment_import_assign_failed');
        expect(s.results.value).toEqual([]);

        getJson.mockImplementationOnce(() => new Promise(() => undefined));
        s.start(null);
        const signal = getJson.mock.calls[1][1].signal as AbortSignal;
        s.stop();

        expect(signal.aborted).toBe(true);
        expect(s.loading.value).toBe(false);
    });
});
