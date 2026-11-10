import { describe, expect, it } from 'vitest';
import { createApp, defineComponent, h, ref } from 'vue';
import { createI18n } from 'vue-i18n';
import sk from '../../../lang/sk/app.json';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import ExpenseLinesField from './ExpenseLinesField.vue';
import { emptyLine, type ExpenseLineKind, type ExpenseLineRow } from '@/utils/expenseLines';

const options = [
    { value: 'm1', label: 'Anna Test' },
    { value: 'm2', label: 'Boris Test' },
];

function mount(kind: ExpenseLineKind, initial: ExpenseLineRow[], errors: Record<string, string> = {}) {
    const lines = ref(initial);
    const host = document.createElement('div');
    document.body.appendChild(host);
    createApp(
        defineComponent({
            render: () =>
                h(FormProvider, { form: {} as never }, () =>
                    h(ExpenseLinesField, {
                        kind,
                        lines: lines.value,
                        employeeOptions: options,
                        errors,
                        disabled: false,
                        'onUpdate:lines': (value: ExpenseLineRow[]) => (lines.value = value),
                    }),
                ),
        }),
    )
        .use(createI18n({ legacy: false, locale: 'sk', messages: { sk }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    return { host, lines };
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('ExpenseLinesField', () => {
    it('renders an employee select and amount per row with the printed-name hint', () => {
        const { host } = mount('employee', [
            { ...emptyLine(), printed_name: 'Unknown Person', amount: '100.00' },
            { ...emptyLine(), tenant_membership_id: 'm1', amount: '50.50' },
        ]);

        expect(host.querySelectorAll('[data-autom=expense-line]')).toHaveLength(2);
        expect(host.querySelectorAll('select')).toHaveLength(2);
        expect(host.textContent).toContain('V doklade: Unknown Person');
        expect(host.querySelector('[data-autom=expense-lines-total]')?.textContent).toContain('150');
    });

    it('renders recipient rows as text inputs without a select', () => {
        const { host } = mount('recipient', [{ ...emptyLine(), supplier_name: 'Tax office', amount: '10.00' }]);

        expect(host.querySelectorAll('select')).toHaveLength(0);
        expect((host.querySelector('input') as HTMLInputElement).value).toBe('Tax office');
    });

    it('shows row errors on the matching row only', () => {
        const { host } = mount('employee', [emptyLine(), emptyLine()], {
            'lines.1.tenant_membership_id': 'Pick someone',
        });
        const rows = host.querySelectorAll('[data-autom=expense-line]');

        expect(rows[0]?.textContent).not.toContain('Pick someone');
        expect(rows[1]?.textContent).toContain('Pick someone');
    });

    it('shows the backend row error filed under supplier_name on an employee row', () => {
        const { host } = mount('employee', [emptyLine()], { 'lines.0.supplier_name': 'Row needs an employee' });

        expect(host.querySelector('[data-autom=expense-line]')?.textContent).toContain('Row needs an employee');
    });

    it('adds a row, and removes one only while more than one remains', async () => {
        const { host, lines } = mount('employee', [emptyLine()]);

        expect(host.querySelector('button[aria-label^="Odstrániť riadok"]')).toBeNull();

        const add = [...host.querySelectorAll('button')].find((b) => b.textContent?.includes('Pridať zamestnanca'));
        add?.click();
        await flush();

        expect(lines.value).toHaveLength(2);

        (host.querySelector('button[aria-label="Odstrániť riadok 1"]') as HTMLElement).click();
        await flush();

        expect(lines.value).toHaveLength(1);
    });

    it('disables add at 50 rows', () => {
        const { host } = mount(
            'employee',
            Array.from({ length: 50 }, () => emptyLine()),
        );
        const add = [...host.querySelectorAll('button')].find((b) => b.textContent?.includes('Pridať zamestnanca'));

        expect(add?.hasAttribute('disabled')).toBe(true);
    });
});
