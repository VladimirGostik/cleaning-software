import { describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import sk from '../../../lang/sk/app.json';
import ExpenseImportResultCard from './ExpenseImportResultCard.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (p, { slots }) =>
            () =>
                h('a', { href: p.href }, slots.default?.()),
    }),
}));

function render(result: App.Data.Expenses.ExpenseImportResultData): HTMLElement {
    const host = document.createElement('div');
    createApp(defineComponent({ render: () => h(ExpenseImportResultCard, { result }) }))
        .use(createI18n({ legacy: false, locale: 'sk', messages: { sk }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    return host;
}

describe('ExpenseImportResultCard', () => {
    it('renders all three counts in a status region', () => {
        const host = render({ created: 3, already_imported: 7, excluded: 2 });
        const values = [...host.querySelectorAll('dd')].map((d) => d.textContent);

        expect(values).toEqual(['3', '7', '2']);
        expect(host.querySelector('[role=status]')).not.toBeNull();
    });

    it('shows the nothing-new title and no drafts link when created is 0', () => {
        const host = render({ created: 0, already_imported: 5, excluded: 0 });
        const hrefs = [...host.querySelectorAll('a')].map((a) => a.getAttribute('href'));

        expect(host.textContent).toContain('Nič nové sa nepridalo');
        expect(hrefs).toEqual(['/expenses/import']);
    });

    it('links to the draft filter when created is above 0', () => {
        const host = render({ created: 1, already_imported: 0, excluded: 0 });
        const hrefs = [...host.querySelectorAll('a')].map((a) => a.getAttribute('href'));

        expect(host.textContent).toContain('Import dokončený');
        expect(hrefs).toEqual(['/expenses?filter[status]=draft', '/expenses/import']);
    });
});
