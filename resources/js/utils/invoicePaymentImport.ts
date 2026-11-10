import { normalizeSearchText } from '@/utils/text';

export type PaymentImportFilter = 'all' | 'review' | 'matched' | 'partial' | 'skipped';

export type PaymentImportRow = {
    -readonly [K in keyof App.Data.Invoices.PaymentImportRowData]: App.Data.Invoices.PaymentImportRowData[K];
} & { manual_invoice: App.Data.Invoices.PaymentImportInvoiceData | null };

export type RowProjection = {
    amount_to_record: string;
    excess: string;
    outcome: 'matched' | 'partial' | 'overpaid' | 'nothing';
};

export const PAYMENT_IMPORT_FILTERS: readonly PaymentImportFilter[] = [
    'review',
    'matched',
    'partial',
    'skipped',
    'all',
];

export const FILTER_OF_STATE: Record<App.Enums.PaymentImportRowStateEnum, Exclude<PaymentImportFilter, 'all'>> = {
    already_imported: 'skipped',
    no_vs: 'review',
    vs_not_found: 'review',
    invoice_not_payable: 'review',
    invoice_paid: 'review',
    previously_deleted: 'review',
    possible_duplicate: 'review',
    overpaid: 'review',
    partial: 'partial',
    matched: 'matched',
};

const UNPAYABLE_STATES: readonly App.Enums.PaymentImportRowStateEnum[] = ['invoice_paid', 'invoice_not_payable'];

function toCents(value: string): number {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
}

function fromCents(cents: number): string {
    const sign = cents < 0 ? '-' : '';
    const abs = Math.abs(cents);

    return `${sign}${Math.floor(abs / 100)}.${String(abs % 100).padStart(2, '0')}`;
}

export function toRow(data: App.Data.Invoices.PaymentImportRowData): PaymentImportRow {
    return { ...data, manual_invoice: null };
}

export function effectiveInvoice(row: PaymentImportRow): App.Data.Invoices.PaymentImportInvoiceData | null {
    return row.manual_invoice ?? row.invoice;
}

export function canAssign(row: PaymentImportRow): boolean {
    return row.state !== 'already_imported';
}

export function canInclude(row: PaymentImportRow): boolean {
    if (row.state === 'already_imported' || effectiveInvoice(row) === null) return false;

    return row.manual_invoice !== null || !UNPAYABLE_STATES.includes(row.state);
}

export function sumAmounts(values: readonly string[]): string {
    return fromCents(values.reduce((sum, value) => sum + toCents(value), 0));
}

export function projectRows(rows: readonly PaymentImportRow[]): Map<string, RowProjection | null> {
    const ordered = [...rows].sort((a, b) => a.date.localeCompare(b.date) || a.reference.localeCompare(b.reference));
    const balances = new Map<string, number>();
    const result = new Map<string, RowProjection | null>();

    for (const row of ordered) {
        const invoice = effectiveInvoice(row);
        if (invoice === null || row.state === 'already_imported') {
            result.set(row.reference, null);
            continue;
        }

        const unpayable = row.manual_invoice === null && UNPAYABLE_STATES.includes(row.state);
        const balance = unpayable ? 0 : (balances.get(invoice.id) ?? toCents(invoice.balance_due));
        const open = Math.max(balance, 0);
        const amount = toCents(row.amount);
        const record = Math.min(amount, open);
        const excess = Math.max(0, amount - open);

        let outcome: RowProjection['outcome'] = 'matched';
        if (open === 0) outcome = 'nothing';
        else if (amount > open) outcome = 'overpaid';
        else if (amount < open) outcome = 'partial';

        result.set(row.reference, { amount_to_record: fromCents(record), excess: fromCents(excess), outcome });
        if (row.include && !unpayable) balances.set(invoice.id, open - record);
    }

    return result;
}

export function matchesSearch(row: PaymentImportRow, query: string): boolean {
    const needle = normalizeSearchText(query);
    if (needle === '') return true;

    const invoice = effectiveInvoice(row);
    const haystack = [
        row.payer_name,
        row.payer_iban,
        row.variable_symbol,
        row.note,
        invoice?.number,
        invoice?.customer_name,
    ]
        .filter((part): part is string => typeof part === 'string' && part !== '')
        .map(normalizeSearchText)
        .join(' ');

    return haystack.includes(needle);
}

export function validateFiles(
    files: readonly File[],
    limits: App.Data.Invoices.PaymentImportLimitsData,
): { key: string; params: Record<string, number> } | null {
    if (files.length > limits.max_files) {
        return { key: 'invoice_payment_import_too_many_files', params: { max: limits.max_files } };
    }

    if (files.some((file) => file.size > limits.max_file_kb * 1024)) {
        return { key: 'invoice_payment_import_file_too_large', params: { max: limits.max_file_kb / 1024 } };
    }

    return null;
}

export function rowInputs(rows: readonly PaymentImportRow[]): App.Data.Invoices.PaymentImportRowInputData[] {
    return rows
        .filter((row) => row.state !== 'already_imported')
        .map((row) => ({
            reference: row.reference,
            include: row.include,
            invoice_id: row.manual_invoice?.id ?? null,
        }));
}
