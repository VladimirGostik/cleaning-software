import { describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import sk from '../../../lang/sk/app.json';
import EmployeeExpensesCard from './EmployeeExpensesCard.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (p, { slots }) =>
            () =>
                h('a', { href: p.href }, slots.default?.()),
    }),
}));

function expense(partial: Partial<App.Data.Expenses.ExpenseListItemData>): App.Data.Expenses.ExpenseListItemData {
    return {
        id: 'e1',
        tenant_id: 't1',
        tenant_name: 'Test Firm',
        status: 'confirmed',
        date: '2026-11-05',
        amount: '900.00',
        currency: 'EUR',
        vat_amount: null,
        supplier_name: null,
        category: 'wages',
        source: 'manual',
        has_receipt: false,
        proposed_category: null,
        tenant_membership_id: 'm1',
        employee_name: 'Anna Test',
        payroll_month: '2026-10',
        is_undocumented: false,
        ...partial,
    } as App.Data.Expenses.ExpenseListItemData;
}

function render(expenses: App.Data.Expenses.ExpenseListItemData[]): HTMLElement {
    const host = document.createElement('div');
    createApp(defineComponent({ render: () => h(EmployeeExpensesCard, { employeeId: 'm1', expenses }) }))
        .use(createI18n({ legacy: false, locale: 'sk', messages: { sk }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    return host;
}

describe('EmployeeExpensesCard', () => {
    it('shows the empty text when there are no expenses', () => {
        const host = render([]);

        expect(host.querySelectorAll('[data-autom=employee-payslip]')).toHaveLength(0);
        expect(host.querySelectorAll('[data-autom=employee-cash]')).toHaveLength(0);
        expect(host.querySelectorAll('a')).toHaveLength(0);
    });

    it('links the PDF only for a payslip that has one', () => {
        const host = render([
            expense({ id: 'with', has_receipt: true }),
            expense({ id: 'without', has_receipt: false, payroll_month: '2026-09' }),
        ]);
        const rows = host.querySelectorAll('[data-autom=employee-payslip]');

        expect(rows).toHaveLength(2);
        expect(rows[0]?.querySelector('a[href="/expenses/with/receipt"]')).not.toBeNull();
        expect(rows[1]?.querySelector('a[href$="/receipt"]')).toBeNull();
        expect(rows[0]?.textContent).toContain('10/2026');
    });

    it('lists cash entries with date and note, separate from payslips', () => {
        const host = render([
            expense({ id: 'cash1', is_undocumented: true, payroll_month: null, description: 'Advance for supplies' }),
        ]);

        expect(host.querySelectorAll('[data-autom=employee-payslip]')).toHaveLength(0);
        const cash = host.querySelector('[data-autom=employee-cash]');
        expect(cash?.textContent).toContain('5. 11. 2026');
        expect(cash?.textContent).toContain('Advance for supplies');
    });

    it('carries the employee filter in the all-links', () => {
        const host = render([expense({ id: 'p' }), expense({ id: 'c', is_undocumented: true, payroll_month: null })]);
        const hrefs = [...host.querySelectorAll('a')].map((a) => a.getAttribute('href'));

        expect(hrefs).toContain('/expenses?filter[tenant_membership_id]=m1');
        expect(hrefs).toContain('/expenses?filter[tenant_membership_id]=m1&filter[undocumented]=1');
    });
});
