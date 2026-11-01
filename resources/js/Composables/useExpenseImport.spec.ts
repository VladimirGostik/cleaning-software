import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { RequestError } from '@/utils/jsonRequest';
import { useExpenseImport } from './useExpenseImport';

const postJson = vi.fn();

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn() } }));
vi.mock('@/utils/jsonRequest', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/jsonRequest')>()),
    postJson: (...args: unknown[]) => postJson(...args),
}));

function row(
    reference: string,
    state: App.Enums.ExpenseImportRowStateEnum,
    include = state === 'new',
): App.Data.Expenses.ExpenseImportRowData {
    return {
        reference,
        date: '2026-10-01',
        amount: '10.00',
        counterparty: null,
        note: null,
        proposed_category: null,
        confidence: 0,
        state,
        existing_expense_id: state === 'already_imported' ? 'e1' : null,
        include,
    };
}

function previewOf(rows: App.Data.Expenses.ExpenseImportRowData[]): App.Data.Expenses.ExpenseImportPreviewData {
    return {
        account_check: {
            status: 'matched',
            statement_iban: 'SK0000000000000000000001',
            owner_tenant_id: null,
            owner_tenant_name: null,
        },
        rows,
        skipped_credit_rows: 2,
        skipped_other_rows: 1,
    };
}

async function loaded(rows: App.Data.Expenses.ExpenseImportRowData[]) {
    postJson.mockResolvedValueOnce(previewOf(rows));
    const flow = useExpenseImport();
    await flow.load(new File(['x'], 'a.xml'));

    return flow;
}

const MIXED = [
    row('r1', 'new'),
    row('r2', 'already_imported'),
    row('r3', 'possible_match'),
    row('r4', 'previously_deleted'),
    row('r5', 'new', false),
];

describe('useExpenseImport', () => {
    beforeEach(() => {
        postJson.mockReset();
        vi.mocked(router.post).mockReset();
    });

    it('posts every row in order with account_iban and without state fields', async () => {
        const flow = await loaded(MIXED);
        flow.submit();

        const [url, payload] = vi.mocked(router.post).mock.calls[0] as unknown as [
            string,
            App.Data.Expenses.ExpenseImportData,
        ];
        expect(url).toBe('/expenses/import');
        expect(payload.account_iban).toBe('SK0000000000000000000001');
        expect(payload.rows.map((r) => r.reference)).toEqual(['r1', 'r2', 'r3', 'r4', 'r5']);
        expect(payload.rows.map((r) => r.include)).toEqual([true, false, false, false, false]);
        for (const r of payload.rows) {
            expect(r).not.toHaveProperty('state');
            expect(r).not.toHaveProperty('existing_expense_id');
        }
    });

    it('passes preserveState errors', async () => {
        const flow = await loaded(MIXED);
        flow.submit();

        const options = vi.mocked(router.post).mock.calls[0][2];
        expect(options?.preserveState).toBe('errors');
    });

    it('does not post without a preview', () => {
        useExpenseImport().submit();
        expect(router.post).not.toHaveBeenCalled();
    });

    it('setInclude ignores already_imported and works on the other states', async () => {
        const flow = await loaded(MIXED);
        flow.setInclude('r2', true);
        flow.setInclude('r3', true);
        flow.setInclude('r4', true);

        const byRef = Object.fromEntries(flow.rows.value.map((r) => [r.reference, r.include]));
        expect(byRef).toMatchObject({ r2: false, r3: true, r4: true });
    });

    it('counts a mixed preview', async () => {
        const flow = await loaded(MIXED);
        flow.setInclude('r3', true);

        expect(flow.counts.value).toEqual({
            new: 2,
            already_imported: 1,
            possible_match: 1,
            previously_deleted: 1,
        });
        expect(flow.selectedCount.value).toBe(2);
        expect(flow.totalMovements.value).toBe(5 + 2 + 1);
    });

    it('allAlreadyImported is false on empty rows and on a mix, true when all are imported', async () => {
        expect((await loaded([])).allAlreadyImported.value).toBe(false);
        expect((await loaded(MIXED)).allAlreadyImported.value).toBe(false);
        expect(
            (await loaded([row('a', 'already_imported'), row('b', 'already_imported')])).allAlreadyImported.value,
        ).toBe(true);
    });

    it('clear removes preview, rows and error', async () => {
        const flow = await loaded(MIXED);
        flow.error.value = 'boom';
        flow.clear();

        expect(flow.preview.value).toBeNull();
        expect(flow.rows.value).toEqual([]);
        expect(flow.error.value).toBeNull();
    });

    it('a failed load sets the error and leaves no preview', async () => {
        const flow = await loaded(MIXED);
        postJson.mockRejectedValueOnce(new RequestError(422, 'bad file'));
        await flow.load(new File(['x'], 'b.xml'));

        expect(flow.error.value).toBe('bad file');
        expect(flow.preview.value).toBeNull();
        expect(flow.rows.value).toEqual([]);
        expect(flow.loading.value).toBe(false);
    });

    it('onError keeps unique messages', async () => {
        const flow = await loaded(MIXED);
        flow.submit();

        const options = vi.mocked(router.post).mock.calls[0][2] as unknown as {
            onError: (errors: Record<string, string>) => void;
        };
        options.onError({ rows: 'same', 'rows.0.amount': 'same', account_iban: 'other' });

        expect(flow.submitErrors.value).toEqual(['same', 'other']);
    });

    it('ignores a second submit while one is in flight', async () => {
        const flow = await loaded(MIXED);
        flow.submit();
        const options = vi.mocked(router.post).mock.calls[0][2] as unknown as { onStart: () => void };
        options.onStart();
        flow.submit();

        expect(router.post).toHaveBeenCalledTimes(1);
    });
});
