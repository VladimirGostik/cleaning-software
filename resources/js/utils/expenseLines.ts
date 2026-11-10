import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

export type ExpenseLineKind = 'employee' | 'recipient';

export interface ExpenseLineRow {
    key: string;
    tenant_membership_id: string;
    supplier_name: string;
    amount: string;
    printed_name: string | null;
}

export const MAX_EXPENSE_LINES = 50;

let lineCounter = 0;

function nextKey(): string {
    lineCounter += 1;

    return `line-${lineCounter}`;
}

function normalizeDecimal(value: string): string {
    return value.replace(/\s+/g, '').replace(',', '.');
}

function nullable(value: string): string | null {
    return value === '' ? null : value;
}

export function lineKindFor(category: string, isEdit: boolean, hasEmployees: boolean): ExpenseLineKind | null {
    if (isEdit) return null;
    if (category === 'payroll_levies') return 'recipient';
    if (category === 'wages' && hasEmployees) return 'employee';

    return null;
}

export function emptyLine(): ExpenseLineRow {
    return { key: nextKey(), tenant_membership_id: '', supplier_name: '', amount: '', printed_name: null };
}

export function linesFromDraft(lines: readonly App.Data.Expenses.ExpenseDraftLineData[]): ExpenseLineRow[] {
    return lines.map((line) => ({
        key: nextKey(),
        tenant_membership_id: line.tenant_membership_id ?? '',
        supplier_name: line.name ?? '',
        amount: line.amount ?? '',
        printed_name: line.tenant_membership_id ? null : line.name,
    }));
}

export function linesTotal(lines: readonly ExpenseLineRow[]): string {
    const cents = lines.reduce((sum, line) => {
        const parsed = parseFloat(normalizeDecimal(line.amount));

        return Number.isFinite(parsed) ? sum + Math.round(parsed * 100) : sum;
    }, 0);

    return (cents / 100).toFixed(2);
}

export function batchPayload(
    header: {
        tenant_id?: string;
        date: string;
        category: string;
        description: string;
        payroll_month: string;
        ai_proposal: App.Data.Expenses.ExpenseProposalData | null;
        duplicate_action: App.Enums.ExpenseDuplicateActionEnum | null;
    },
    kind: ExpenseLineKind,
    lines: readonly ExpenseLineRow[],
    receiptUuid: string | null,
): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        date: header.date,
        category: header.category,
        currency: 'EUR',
        supplier_name: null,
        description: nullable(header.description),
        payroll_month: nullable(header.payroll_month),
        receipt_uuid: receiptUuid,
        ai_proposal: header.ai_proposal,
        duplicate_action: header.duplicate_action,
        lines: lines.map((line) => ({
            tenant_membership_id: kind === 'employee' ? nullable(line.tenant_membership_id) : null,
            supplier_name: kind === 'recipient' ? nullable(line.supplier_name) : null,
            amount: nullable(normalizeDecimal(line.amount)),
            description: null,
        })),
    };

    if (header.tenant_id) payload.tenant_id = header.tenant_id;

    return payload;
}

export function employeeOptionsFor(
    employees: readonly App.Data.Expenses.ExpenseEmployeeOptionData[],
    tenantId: string,
    inactiveLabel: string,
): SelectOption[] {
    const own = employees.filter((e) => e.tenant_id === tenantId);

    return [...own.filter((e) => e.is_active), ...own.filter((e) => !e.is_active)].map((e) => ({
        value: e.id,
        label: e.is_active ? e.label : `${e.label} (${inactiveLabel})`,
    }));
}

export function duplicateMessages(errors: Record<string, string>): string[] {
    if (!errors.duplicate) return [];

    const specific = Object.entries(errors)
        .filter(([key]) => key === 'tenant_membership_id' || /^lines\.\d+\.tenant_membership_id$/.test(key))
        .sort(([a], [b]) =>
            (a.match(/\d+/)?.[0] ?? '0').localeCompare(b.match(/\d+/)?.[0] ?? '0', undefined, { numeric: true }),
        )
        .map(([, message]) => message);

    return specific.length > 0 ? specific : [errors.duplicate];
}
