import { describe, expect, it } from 'vitest';
import {
    batchPayload,
    duplicateMessages,
    emptyLine,
    employeeOptionsFor,
    lineKindFor,
    linesFromDraft,
    linesTotal,
    type ExpenseLineRow,
} from './expenseLines';

const header = {
    tenant_id: 'tenant-b',
    date: '2026-11-05',
    category: 'wages',
    description: '',
    payroll_month: '2026-10',
    ai_proposal: null,
    duplicate_action: null,
};

function row(partial: Partial<ExpenseLineRow>): ExpenseLineRow {
    return { ...emptyLine(), ...partial };
}

describe('lineKindFor', () => {
    it('picks the mode from category, edit and employees', () => {
        expect(lineKindFor('wages', false, true)).toBe('employee');
        expect(lineKindFor('wages', false, false)).toBeNull();
        expect(lineKindFor('payroll_levies', false, false)).toBe('recipient');
        expect(lineKindFor('wages', true, true)).toBeNull();
        expect(lineKindFor('fuel', false, true)).toBeNull();
    });
});

describe('linesFromDraft', () => {
    it('keeps the printed name only for unmatched lines', () => {
        const rows = linesFromDraft([
            {
                name: 'Anna Test',
                amount: '900.50',
                payroll_month: '2026-10',
                tenant_membership_id: 'm1',
                employee_label: 'Test Anna',
            },
            {
                name: 'Unknown Person',
                amount: '400.00',
                payroll_month: null,
                tenant_membership_id: null,
                employee_label: null,
            },
        ]);

        expect(rows[0]).toMatchObject({ tenant_membership_id: 'm1', amount: '900.50', printed_name: null });
        expect(rows[1]).toMatchObject({ tenant_membership_id: '', amount: '400.00', printed_name: 'Unknown Person' });
        expect(rows[0]?.key).not.toBe(rows[1]?.key);
    });
});

describe('linesTotal', () => {
    it('sums cents-safe and ignores invalid amounts', () => {
        const lines = [
            row({ amount: '0.10' }),
            row({ amount: '0,20' }),
            row({ amount: '1 000.05' }),
            row({ amount: 'x' }),
        ];

        expect(linesTotal(lines)).toBe('1000.35');
        expect(linesTotal([])).toBe('0.00');
    });
});

describe('batchPayload', () => {
    it('sends employee lines with membership ids only and one receipt uuid', () => {
        const payload = batchPayload(
            header,
            'employee',
            [
                row({ tenant_membership_id: 'm1', amount: '1 200,50', supplier_name: 'ignored', printed_name: 'x' }),
                row({ tenant_membership_id: '', amount: '300' }),
            ],
            'uuid-1',
        );

        expect(payload).toMatchObject({
            tenant_id: 'tenant-b',
            currency: 'EUR',
            description: null,
            payroll_month: '2026-10',
            receipt_uuid: 'uuid-1',
            supplier_name: null,
        });
        expect(payload.lines).toEqual([
            { tenant_membership_id: 'm1', supplier_name: null, amount: '1200.50', description: null },
            { tenant_membership_id: null, supplier_name: null, amount: '300', description: null },
        ]);
        expect(JSON.stringify(payload)).not.toContain('printed_name');
        expect(JSON.stringify(payload)).not.toContain('"key"');
    });

    it('sends recipient lines by supplier name', () => {
        const payload = batchPayload(
            { ...header, tenant_id: undefined, category: 'payroll_levies' },
            'recipient',
            [row({ supplier_name: 'Tax office', amount: '10.00', tenant_membership_id: 'm1' })],
            null,
        );

        expect(payload).not.toHaveProperty('tenant_id');
        expect(payload.receipt_uuid).toBeNull();
        expect(payload.lines).toEqual([
            { tenant_membership_id: null, supplier_name: 'Tax office', amount: '10.00', description: null },
        ]);
    });
});

describe('employeeOptionsFor', () => {
    it('keeps one company, active first, inactive labelled', () => {
        const options = employeeOptionsFor(
            [
                { id: 'a', tenant_id: 't1', label: 'Old Employee', is_active: false },
                { id: 'b', tenant_id: 't2', label: 'Other Firm', is_active: true },
                { id: 'c', tenant_id: 't1', label: 'Active Employee', is_active: true },
            ],
            't1',
            'neaktívny',
        );

        expect(options).toEqual([
            { value: 'c', label: 'Active Employee' },
            { value: 'a', label: 'Old Employee (neaktívny)' },
        ]);
    });
});

describe('duplicateMessages', () => {
    it('returns nothing without the duplicate error', () => {
        expect(duplicateMessages({ 'lines.0.amount': 'bad' })).toEqual([]);
    });

    it('collects employee conflict messages in row order', () => {
        expect(
            duplicateMessages({
                duplicate: 'generic',
                'lines.10.tenant_membership_id': 'ten',
                'lines.2.tenant_membership_id': 'two',
                'lines.0.amount': 'ignored',
            }),
        ).toEqual(['two', 'ten']);
    });

    it('reads the single-form key and falls back to the generic message', () => {
        expect(duplicateMessages({ duplicate: 'generic', tenant_membership_id: 'single' })).toEqual(['single']);
        expect(duplicateMessages({ duplicate: 'generic' })).toEqual(['generic']);
    });
});
