import { describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import InvoiceLinksCard from './InvoiceLinksCard.vue';
import InvoiceMetaGrid from './InvoiceMetaGrid.vue';
import InvoicePartiesBlock from './InvoicePartiesBlock.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}));

const messages = { en: { country_sk: 'Slovensko', invoice_section_object: 'Object', invoice_link_object: 'Object' } };

function mount(component: object, invoice: Record<string, unknown>): HTMLElement {
    const host = document.createElement('div');
    createApp({ render: () => h(component, { invoice }) })
        .use(createI18n({ legacy: false, locale: 'en', messages, missingWarn: false, fallbackWarn: false }))
        .mount(host);
    return host;
}

const invoice = {
    supplier: { name: 'Supplier s.r.o.', country: 'SK' },
    customer_name: 'Buyer',
    customer_country: 'SK',
    object_name: 'Hlavna budova',
    object_street: 'Hlavna 1',
    client_id: 'c1',
    client_name: 'Client',
    recurring_invoice_id: null,
    quote_id: null,
    period_from: null,
    period_to: null,
};

describe('invoice detail blocks', () => {
    it('prints the country name instead of the code for supplier and buyer', () => {
        const text = mount(InvoicePartiesBlock, invoice).textContent ?? '';

        expect(text.match(/Slovensko/g)).toHaveLength(2);
        expect(text).not.toMatch(/\bSK\b/);
    });

    it('keeps an unknown country code as is', () => {
        const text = mount(InvoicePartiesBlock, { ...invoice, customer_country: 'XX' }).textContent ?? '';

        expect(text).toContain('XX');
    });

    it('does not render the object in the meta grid', () => {
        const text = mount(InvoiceMetaGrid, { ...invoice, payment_type: 'bank_transfer' }).textContent ?? '';

        expect(text).not.toContain('Hlavna budova');
        expect(text).not.toContain('Hlavna 1');
    });

    it('does not render the object in the links card', () => {
        const text = mount(InvoiceLinksCard, invoice).textContent ?? '';

        expect(text).not.toContain('Hlavna budova');
    });
});
