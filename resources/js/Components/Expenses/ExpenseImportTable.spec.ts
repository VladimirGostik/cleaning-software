import { describe, expect, it } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import sk from '../../../lang/sk/app.json';
import ExpenseImportTable from './ExpenseImportTable.vue';
import type { ImportRow } from '@/Composables/useExpenseImport';

function row(reference: string, state: ImportRow['state'], over: Partial<ImportRow> = {}): ImportRow {
    return {
        reference,
        date: '2026-10-01',
        amount: '12.50',
        counterparty: 'Test s.r.o.',
        note: 'Faktura 1',
        proposed_category: null,
        confidence: 0,
        state,
        existing_expense_id: state === 'already_imported' || state === 'possible_match' ? `id-${reference}` : null,
        include: state === 'new',
        ...over,
    };
}

function render(rows: ImportRow[], emitted: unknown[][] = []): HTMLElement {
    const host = document.createElement('div');
    const Host = defineComponent({
        render: () =>
            h(ExpenseImportTable, {
                rows,
                disabled: false,
                onInclude: (...args: unknown[]) => emitted.push(['include', ...args]),
                onCategory: (...args: unknown[]) => emitted.push(['category', ...args]),
            }),
    });
    createApp(Host)
        .use(createI18n({ legacy: false, locale: 'sk', messages: { sk }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    return host;
}

function line(host: HTMLElement, index: number): HTMLElement {
    return host.querySelectorAll<HTMLElement>('tbody tr')[index];
}

const ALL = [row('n', 'new'), row('a', 'already_imported'), row('p', 'possible_match'), row('d', 'previously_deleted')];

describe('ExpenseImportTable', () => {
    it('shows the text label of each state', () => {
        const host = render(ALL);
        expect(line(host, 0).textContent).toContain('Nový');
        expect(line(host, 1).textContent).toContain('Už importované');
        expect(line(host, 2).textContent).toContain('Možná zhoda');
        expect(line(host, 3).textContent).toContain('Predtým zmazané');
    });

    it('disables checkbox and select of an already imported row only', () => {
        const host = render(ALL);
        const disabled = (i: number) => [
            line(host, i).querySelector<HTMLInputElement>('input[type=checkbox]')!.disabled,
            line(host, i).querySelector<HTMLSelectElement>('select')!.disabled,
        ];
        expect(disabled(1)).toEqual([true, true]);
        expect(disabled(0)).toEqual([false, false]);
        expect(disabled(2)).toEqual([false, false]);
        expect(disabled(3)).toEqual([false, false]);
    });

    it('links to the existing expense in a new tab for already_imported and possible_match', () => {
        const host = render(ALL);
        const link = (i: number) => line(host, i).querySelector('a');
        expect(link(0)).toBeNull();
        expect(link(3)).toBeNull();
        for (const i of [1, 2]) {
            expect(link(i)!.getAttribute('target')).toBe('_blank');
            expect(link(i)!.getAttribute('rel')).toContain('noopener');
        }
        expect(link(1)!.getAttribute('href')).toBe('/expenses/id-a');
    });

    it('emits include and category with the reference, empty category as null', () => {
        const emitted: unknown[][] = [];
        const host = render([row('p', 'possible_match')], emitted);

        const checkbox = line(host, 0).querySelector<HTMLInputElement>('input[type=checkbox]')!;
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change'));

        const select = line(host, 0).querySelector<HTMLSelectElement>('select')!;
        select.value = 'rent';
        select.dispatchEvent(new Event('change'));
        select.value = '';
        select.dispatchEvent(new Event('change'));

        expect(emitted).toEqual([
            ['include', 'p', true],
            ['category', 'p', 'rent'],
            ['category', 'p', null],
        ]);
    });

    it('shows the dash for a missing counterparty and note', () => {
        const host = render([row('n', 'new', { counterparty: null, note: null })]);
        const cells = line(host, 0).querySelectorAll('td');
        expect(cells[4].textContent?.trim()).toBe('—');
        expect(cells[5].textContent?.trim()).toBe('—');
    });

    it('puts the state column right after the checkbox', () => {
        const host = render([row('n', 'new')]);
        const heads = [...host.querySelectorAll('thead th')].map((th) => th.textContent?.trim());
        expect(heads.slice(0, 3)).toEqual(['Importovať', 'Stav', 'Dátum']);
        expect(line(host, 0).querySelectorAll('td')[1].textContent).toContain('Nový');
    });

    it('names each select and checkbox with the row date and amount', () => {
        const host = render([row('n', 'new')]);
        const select = line(host, 0).querySelector('select')!;
        const legend = select.closest('fieldset')!.querySelector('legend')!;
        expect(legend.textContent).toContain('Kategória');
        expect(legend.textContent).toContain('1. 10. 2026');
        expect(legend.textContent).toContain('12,50');
        expect(line(host, 0).querySelector('label')!.textContent).toContain('1. 10. 2026');
    });
});
