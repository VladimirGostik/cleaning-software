import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { RequestError } from '@/utils/jsonRequest';
import { useInvoicePaymentImport } from './useInvoicePaymentImport';

const postJson = vi.fn();

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn() } }));
vi.mock('@/utils/jsonRequest', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/jsonRequest')>()),
    postJson: (...args: unknown[]) => postJson(...args),
}));

const LIMITS = { max_files: 3, max_file_kb: 1024, max_rows: 500 };

function invoice(id: string, balance = '100.00'): App.Data.Invoices.PaymentImportInvoiceData {
    return {
        id,
        number: `FA-${id}`,
        customer_name: 'Zakaznik',
        status: 'issued',
        issue_date: '2026-10-01',
        due_date: '2026-10-15',
        total: balance,
        balance_due: balance,
    };
}

function row(
    reference: string,
    state: App.Enums.PaymentImportRowStateEnum,
    amount = '100.00',
    inv: App.Data.Invoices.PaymentImportInvoiceData | null = invoice('a'),
): App.Data.Invoices.PaymentImportRowData {
    return {
        reference,
        file_index: 0,
        date: '2026-10-05',
        amount,
        payer_name: null,
        payer_iban: null,
        variable_symbol: null,
        note: null,
        state,
        invoice: inv,
        balance_before: null,
        amount_to_record: null,
        excess: null,
        instalment_position: null,
        instalment_count: null,
        existing_payment_id: null,
        include: state === 'matched' || state === 'partial',
    };
}

function previewOf(rows: App.Data.Invoices.PaymentImportRowData[]): App.Data.Invoices.PaymentImportPreviewData {
    return {
        files: [
            {
                index: 0,
                name: 'a.xml',
                iban: 'SK0000000000000000000001',
                period_from: null,
                period_to: null,
                credit_rows: rows.length,
                account: {
                    status: 'matched',
                    statement_iban: 'SK0000000000000000000001',
                    owner_tenant_id: null,
                    owner_tenant_name: null,
                },
            },
        ],
        rows,
        ignored_debit_rows: 0,
        unreadable_credit_rows: 0,
        overlapping_rows: 0,
    };
}

const file = (name = 'a.xml') => new File(['x'], name);

async function loaded(rows: App.Data.Invoices.PaymentImportRowData[]) {
    postJson.mockResolvedValueOnce(previewOf(rows));
    const flow = useInvoicePaymentImport(() => LIMITS);
    await flow.load([file('a.xml'), file('b.xml')]);

    return flow;
}

const MIXED = [
    row('r1', 'matched', '100.00', invoice('a')),
    row('r2', 'overpaid', '60.00', invoice('b', '50.00')),
    row('r3', 'no_vs', '20.00', null),
    row('r4', 'already_imported', '5.00', invoice('c')),
];

describe('useInvoicePaymentImport', () => {
    beforeEach(() => {
        postJson.mockReset();
        vi.mocked(router.post).mockReset();
    });

    it('load posts every file as files[] and keeps the files', async () => {
        const flow = await loaded(MIXED);
        const [url, body] = postJson.mock.calls[0] as [string, FormData];

        expect(url).toBe('/invoices/payment-import/preview');
        expect(body.getAll('files[]')).toHaveLength(2);
        expect(flow.files.value).toHaveLength(2);
        expect(flow.rows.value).toHaveLength(4);
    });

    it('opens on review when it has rows, else on all', async () => {
        expect((await loaded(MIXED)).filter.value).toBe('review');
        expect((await loaded([row('r1', 'matched')])).filter.value).toBe('all');
    });

    it('rejects an invalid file set before the request', async () => {
        const flow = useInvoicePaymentImport(() => ({ ...LIMITS, max_files: 1 }));
        await flow.load([file(), file('b.xml')]);

        expect(postJson).not.toHaveBeenCalled();
        expect(flow.error.value).toBe('invoice_payment_import_too_many_files');
    });

    it('computes totals per group', async () => {
        const flow = await loaded(MIXED);

        expect(flow.totals.value.review).toEqual({ count: 2, amount: '80.00' });
        expect(flow.totals.value.matched).toEqual({ count: 1, amount: '100.00' });
        expect(flow.totals.value.skipped).toEqual({ count: 1, amount: '5.00' });
        expect(flow.totals.value.all).toEqual({ count: 4, amount: '185.00' });
    });

    it('selection sums the projected amounts of ticked rows', async () => {
        const flow = await loaded(MIXED);
        flow.setInclude('r2', true);

        expect(flow.selection.value).toEqual({ count: 2, amount: '150.00' });
    });

    it('selection does not count a ticked row whose invoice is already used up', async () => {
        const a = invoice('a', '100.00');
        const flow = await loaded([row('r1', 'matched', '100.00', a), row('r2', 'overpaid', '30.00', a)]);
        flow.setInclude('r2', true);

        expect(flow.selection.value).toEqual({ count: 1, amount: '100.00' });
    });

    it('setIncludeVisible touches only visible includable rows', async () => {
        const flow = await loaded(MIXED);
        flow.filter.value = 'all';
        flow.setIncludeVisible(true);

        expect(flow.rows.value.map((r) => r.include)).toEqual([true, true, false, false]);

        flow.filter.value = 'review';
        flow.setIncludeVisible(false);
        expect(flow.rows.value.map((r) => r.include)).toEqual([true, false, false, false]);
    });

    it('assign ticks the row, unassign restores the server default', async () => {
        const flow = await loaded(MIXED);
        flow.assign('r3', invoice('m', '20.00'));

        expect(flow.rows.value[2].include).toBe(true);
        expect(flow.rows.value[2].manual_invoice?.id).toBe('m');

        flow.unassign('r3');
        expect(flow.rows.value[2].include).toBe(false);
        expect(flow.rows.value[2].manual_invoice).toBeNull();

        flow.assign('r4', invoice('m'));
        expect(flow.rows.value[3].manual_invoice).toBeNull();
    });

    it('submit posts files and rows as form data and collects 422 messages', async () => {
        const flow = await loaded(MIXED);
        flow.submit();

        const [url, payload, options] = vi.mocked(router.post).mock.calls[0] as unknown as [
            string,
            { files: File[]; rows: { reference: string }[] },
            { forceFormData: boolean; onError: (e: Record<string, string>) => void },
        ];

        expect(url).toBe('/invoices/payment-import');
        expect(payload.files).toHaveLength(2);
        expect(payload.rows.map((r) => r.reference)).toEqual(['r1', 'r2', 'r3']);
        expect(options.forceFormData).toBe(true);

        options.onError({ files: 'Chyba', 'files.0': 'Chyba', rows: 'Ina' });
        expect(flow.submitErrors.value).toEqual(['Chyba', 'Ina']);
    });

    it('a request error fills error and clear resets', async () => {
        postJson.mockRejectedValueOnce(new RequestError(429, null));
        const flow = useInvoicePaymentImport(() => LIMITS);
        await flow.load([file()]);

        expect(flow.error.value).toBe('request_throttled');
        expect(flow.loading.value).toBe(false);

        const loadedFlow = await loaded(MIXED);
        loadedFlow.clear();
        expect(loadedFlow.rows.value).toEqual([]);
        expect(loadedFlow.preview.value).toBeNull();
        expect(loadedFlow.files.value).toEqual([]);
    });
});
