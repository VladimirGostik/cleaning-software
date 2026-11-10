import { describe, expect, it } from 'vitest';
import {
    FILTER_OF_STATE,
    canAssign,
    canInclude,
    matchesSearch,
    projectRows,
    rowInputs,
    sumAmounts,
    toRow,
    validateFiles,
    type PaymentImportRow,
} from './invoicePaymentImport';

type State = App.Enums.PaymentImportRowStateEnum;

function invoice(id: string, balance: string): App.Data.Invoices.PaymentImportInvoiceData {
    return {
        id,
        number: `FA-${id}`,
        customer_name: 'Zákazník Čistý',
        status: 'issued',
        issue_date: '2026-10-01',
        due_date: '2026-10-15',
        total: balance,
        balance_due: balance,
    };
}

function row(
    reference: string,
    state: State,
    amount: string,
    inv: App.Data.Invoices.PaymentImportInvoiceData | null,
    over: Partial<PaymentImportRow> = {},
): PaymentImportRow {
    return {
        ...toRow({
            reference,
            file_index: 0,
            date: '2026-10-05',
            amount,
            payer_name: 'Platca A',
            payer_iban: 'SK0000000000000000000002',
            variable_symbol: '1001',
            note: 'Faktúra',
            state,
            invoice: inv,
            balance_before: null,
            amount_to_record: null,
            excess: null,
            instalment_position: null,
            instalment_count: null,
            existing_payment_id: null,
            include: state === 'matched' || state === 'partial',
        }),
        ...over,
    };
}

describe('projectRows', () => {
    it('matches the server numbers on an untouched preview', () => {
        const a = invoice('a', '300.00');
        const b = invoice('b', '50.00');
        const c = invoice('c', '80.00');
        const rows = [
            row('r1', 'partial', '100.00', a, { date: '2026-10-01' }),
            row('r2', 'partial', '100.00', a, { date: '2026-10-02' }),
            row('r3', 'matched', '100.00', a, { date: '2026-10-03' }),
            row('r4', 'overpaid', '70.00', b),
            row('r5', 'possible_duplicate', '80.00', c),
        ];
        const result = projectRows(rows);

        expect(result.get('r1')).toEqual({ amount_to_record: '100.00', excess: '0.00', outcome: 'partial' });
        expect(result.get('r2')).toEqual({ amount_to_record: '100.00', excess: '0.00', outcome: 'partial' });
        expect(result.get('r3')).toEqual({ amount_to_record: '100.00', excess: '0.00', outcome: 'matched' });
        expect(result.get('r4')).toEqual({ amount_to_record: '50.00', excess: '20.00', outcome: 'overpaid' });
        expect(result.get('r5')).toEqual({ amount_to_record: '80.00', excess: '0.00', outcome: 'matched' });
    });

    it('moves the balance on when an earlier instalment is unticked', () => {
        const a = invoice('a', '200.00');
        const rows = [
            row('r1', 'partial', '100.00', a, { date: '2026-10-01', include: false }),
            row('r2', 'partial', '100.00', a, { date: '2026-10-02' }),
        ];

        expect(projectRows(rows).get('r2')).toEqual({
            amount_to_record: '100.00',
            excess: '0.00',
            outcome: 'partial',
        });
    });

    it('records the open amount and reports the rest when an overpaid row is ticked', () => {
        const rows = [row('r1', 'overpaid', '75.50', invoice('a', '70.00'), { include: true })];

        expect(projectRows(rows).get('r1')).toEqual({
            amount_to_record: '70.00',
            excess: '5.50',
            outcome: 'overpaid',
        });
    });

    it('gives nothing when earlier rows used the invoice up', () => {
        const a = invoice('a', '100.00');
        const rows = [
            row('r1', 'matched', '100.00', a, { date: '2026-10-01' }),
            row('r2', 'overpaid', '30.00', a, { date: '2026-10-02', include: true }),
        ];

        expect(projectRows(rows).get('r2')?.outcome).toBe('nothing');
        expect(projectRows(rows).get('r2')?.amount_to_record).toBe('0.00');
    });

    it('treats invoice_paid without a manual pick as nothing and has no projection without invoice', () => {
        const rows = [row('r1', 'invoice_paid', '10.00', invoice('a', '0.00')), row('r2', 'no_vs', '10.00', null)];
        const result = projectRows(rows);

        expect(result.get('r1')?.outcome).toBe('nothing');
        expect(result.get('r2')).toBeNull();
    });

    it('uses the manual invoice over the VS one', () => {
        const rows = [
            row('r1', 'vs_not_found', '40.00', null, { manual_invoice: invoice('m', '40.00'), include: true }),
        ];

        expect(projectRows(rows).get('r1')?.outcome).toBe('matched');
    });
});

describe('sumAmounts', () => {
    it('sums in cents without float drift', () => {
        expect(sumAmounts(['0.10', '0.20'])).toBe('0.30');
        expect(sumAmounts([])).toBe('0.00');
    });
});

describe('canInclude / canAssign', () => {
    const inv = invoice('a', '10.00');

    it('locks already_imported', () => {
        const r = row('r', 'already_imported', '10.00', inv);

        expect(canAssign(r)).toBe(false);
        expect(canInclude(r)).toBe(false);
    });

    it('needs a manual pick for no_vs / vs_not_found', () => {
        for (const state of ['no_vs', 'vs_not_found'] as const) {
            const r = row('r', state, '10.00', null);
            expect(canAssign(r)).toBe(true);
            expect(canInclude(r)).toBe(false);
            expect(canInclude({ ...r, manual_invoice: inv })).toBe(true);
        }
    });

    it('needs a manual pick for invoice_paid / invoice_not_payable', () => {
        for (const state of ['invoice_paid', 'invoice_not_payable'] as const) {
            const r = row('r', state, '10.00', inv);
            expect(canInclude(r)).toBe(false);
            expect(canInclude({ ...r, manual_invoice: inv })).toBe(true);
        }
    });

    it('allows previously_deleted / possible_duplicate / overpaid', () => {
        for (const state of ['previously_deleted', 'possible_duplicate', 'overpaid'] as const) {
            expect(canInclude(row('r', state, '10.00', inv))).toBe(true);
        }
    });
});

describe('FILTER_OF_STATE', () => {
    it('covers all 10 states', () => {
        expect(Object.keys(FILTER_OF_STATE)).toHaveLength(10);
        expect(FILTER_OF_STATE.matched).toBe('matched');
        expect(FILTER_OF_STATE.partial).toBe('partial');
        expect(FILTER_OF_STATE.already_imported).toBe('skipped');
        expect(FILTER_OF_STATE.overpaid).toBe('review');
    });
});

describe('matchesSearch', () => {
    const r = row('r', 'matched', '10.00', invoice('a', '10.00'), { payer_name: 'Žofia Čierna' });

    it('ignores diacritics and case across fields', () => {
        expect(matchesSearch(r, 'zofia cierna')).toBe(true);
        expect(matchesSearch(r, '1001')).toBe(true);
        expect(matchesSearch(r, 'FA-a')).toBe(true);
        expect(matchesSearch(r, 'cisty')).toBe(true);
        expect(matchesSearch(r, 'faktura')).toBe(true);
        expect(matchesSearch(r, 'neexistuje')).toBe(false);
        expect(matchesSearch(r, '')).toBe(true);
    });
});

describe('validateFiles', () => {
    const limits = { max_files: 2, max_file_kb: 1024, max_rows: 500 };
    const file = (size: number) => new File([new Uint8Array(size)], 'a.xml');

    it('reports too many files and too large files', () => {
        expect(validateFiles([file(1), file(1), file(1)], limits)).toEqual({
            key: 'invoice_payment_import_too_many_files',
            params: { max: 2 },
        });
        expect(validateFiles([file(1024 * 1024 + 1)], limits)).toEqual({
            key: 'invoice_payment_import_file_too_large',
            params: { max: 1 },
        });
        expect(validateFiles([file(10)], limits)).toBeNull();
    });
});

describe('rowInputs', () => {
    it('leaves out already_imported and sends invoice_id only for manual picks', () => {
        const inv = invoice('a', '10.00');
        const inputs = rowInputs([
            row('r1', 'already_imported', '10.00', inv),
            row('r2', 'matched', '10.00', inv),
            row('r3', 'no_vs', '10.00', null, { manual_invoice: invoice('m', '10.00'), include: true }),
        ]);

        expect(inputs).toEqual([
            { reference: 'r2', include: true, invoice_id: null },
            { reference: 'r3', include: true, invoice_id: 'm' },
        ]);
    });
});
