import { describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import sk from '../../../lang/sk/app.json';
import InvoicePaymentImportResultCard from './InvoicePaymentImportResultCard.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (p, { slots }) =>
            () =>
                h('a', { href: p.href }, slots.default?.()),
    }),
}));

function render(over: Partial<App.Data.Invoices.PaymentImportResultData> = {}): HTMLElement {
    const result: App.Data.Invoices.PaymentImportResultData = {
        recorded_count: 3,
        recorded_total: '250.00',
        already_imported: 7,
        excluded: 2,
        settled_invoices: [{ id: 'i1', number: 'FA-1', balance_due: '0.00' }],
        partly_paid_invoices: [{ id: 'i2', number: 'FA-2', balance_due: '40.00' }],
        overpayments: [
            { reference: 'r1', invoice_id: 'i3', invoice_number: 'FA-3', payer_name: 'Platca A', excess: '5.00' },
        ],
        skipped: [{ reference: 'r9', state: 'invoice_paid', invoice_number: 'FA-4' }],
        ...over,
    };
    const host = document.createElement('div');
    createApp(defineComponent({ render: () => h(InvoicePaymentImportResultCard, { result }) }))
        .use(createI18n({ legacy: false, locale: 'sk', messages: { sk }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    return host;
}

const hrefs = (host: HTMLElement) => [...host.querySelectorAll('a')].map((a) => a.getAttribute('href'));

describe('InvoicePaymentImportResultCard', () => {
    it('renders the counts in a status region', () => {
        const host = render();
        const values = [...host.querySelectorAll('dd')].map((d) => d.textContent?.replace(/\s+/g, ' ').trim());

        expect(host.querySelector('[role=status]')).not.toBeNull();
        expect(values[0]).toContain('3');
        expect(values.slice(1)).toEqual(['7', '2']);
        expect(host.textContent).toContain('FA-4');
        expect(host.textContent).toContain('Platca A');
    });

    it('links invoices and the follow-up buttons', () => {
        expect(hrefs(render())).toEqual([
            '/invoices/i1',
            '/invoices/i2',
            '/invoices/i3',
            '/invoices?filter[open]=1',
            '/invoices/payment-import',
        ]);
    });

    it('omits empty sections and has no orange CTA', () => {
        const host = render({
            recorded_count: 0,
            settled_invoices: [],
            partly_paid_invoices: [],
            overpayments: [],
            skipped: [],
        });

        expect(hrefs(host)).toEqual(['/invoices?filter[open]=1', '/invoices/payment-import']);
        expect(host.querySelector('.btn-secondary')).toBeNull();
        expect(host.querySelectorAll('section')).toHaveLength(0);
    });
});
