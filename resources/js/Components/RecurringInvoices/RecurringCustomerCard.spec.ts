import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import RecurringCustomerCard from './RecurringCustomerCard.vue';

const state = vi.hoisted(() => ({ can: {} as Record<string, boolean> }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            get can() {
                return state.can;
            },
        },
    }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}));

const messages = {
    en: {
        invoice_pdf_customer: 'Customer',
        invoice_pdf_represented_by: 'Represented by',
        invoice_pdf_ico: 'IČO',
        invoice_pdf_dic: 'DIČ',
        invoice_pdf_vat_number: 'VAT',
        invoice_pdf_email: 'E-mail',
        recurring_invoice_no_customer: 'No customer',
        recurring_invoice_buyer_source_object_billing: 'Buyer from billing of {object}.',
        recurring_invoice_buyer_source_client: 'Buyer from client {client}.',
        recurring_invoice_buyer_source_entered: 'Buyer entered here.',
        recurring_invoice_edit_object_billing: 'Edit billing',
        recurring_invoice_edit_client: 'Edit client',
        recurring_invoice_edit_buyer: 'Edit buyer',
        recurring_invoice_email_missing: 'No e-mail.',
        recurring_invoice_email_missing_auto_send: 'No e-mail, not sent automatically.',
    },
};

function buyer(overrides: Partial<App.Data.Invoices.InvoiceBuyerData> = {}): App.Data.Invoices.InvoiceBuyerData {
    return {
        source: 'object_billing',
        name: 'Bytový dom A',
        represented_by: 'Správca s.r.o.',
        representative: null,
        ico: '12345678',
        dic: '2012345678',
        vat_number: null,
        street: 'Hlavná 1',
        city: 'Žilina',
        postal_code: '010 01',
        country: 'SK',
        email: 'a@b.sk',
        ...overrides,
    };
}

function invoice(
    overrides: Partial<App.Data.RecurringInvoices.RecurringInvoiceDetailData> = {},
): App.Data.RecurringInvoices.RecurringInvoiceDetailData {
    return {
        id: 'ri1',
        status: 'active',
        client_id: 'c1',
        cleaning_object_id: 'o1',
        cleaning_object_name: 'Dom A',
        auto_send: false,
        buyer: buyer(),
        ...overrides,
    } as App.Data.RecurringInvoices.RecurringInvoiceDetailData;
}

function render(ri: App.Data.RecurringInvoices.RecurringInvoiceDetailData): HTMLElement {
    const host = document.createElement('div');
    createApp({ render: () => h(RecurringCustomerCard, { recurringInvoice: ri }) })
        .use(createI18n({ legacy: false, locale: 'en', messages, missingWarn: false, fallbackWarn: false }))
        .mount(host);
    return host;
}

const links = (host: HTMLElement) => [...host.querySelectorAll('a')].map((a) => a.getAttribute('href'));

describe('RecurringCustomerCard', () => {
    beforeEach(() => {
        state.can = { editObjects: true, viewAllObjects: true, editClients: true, editRecurringInvoices: true };
    });

    it('renders the resolved object billing buyer with the source note', () => {
        const text = render(invoice()).textContent ?? '';

        expect(text).toContain('Bytový dom A');
        expect(text).toContain('Represented by: Správca s.r.o.');
        expect(text).toContain('IČO: 12345678');
        expect(text).toContain('DIČ: 2012345678');
        expect(text).toContain('Hlavná 1');
        expect(text).toContain('010 01 Žilina');
        expect(text).toContain('Buyer from billing of Dom A.');
    });

    it('links to the object billing editor with the permission pair', () => {
        expect(links(render(invoice()))).toEqual(['/objects/o1?edit=billing']);

        state.can = { editObjects: true, viewAllObjects: false };
        expect(links(render(invoice()))).toEqual([]);
    });

    it('links to the client when the buyer comes from the client', () => {
        const ri = invoice({ buyer: buyer({ source: 'client', represented_by: null, name: 'Klient' }) });

        expect(links(render(ri))).toEqual(['/clients/c1']);
        expect(render(ri).textContent).toContain('Buyer from client Klient.');

        state.can = { editClients: false };
        expect(links(render(ri))).toEqual([]);
    });

    it('links to the recurring edit only when active or paused and entered', () => {
        const entered = buyer({ source: 'entered', represented_by: null });

        expect(links(render(invoice({ buyer: entered })))).toEqual(['/recurring-invoices/ri1/edit']);
        expect(links(render(invoice({ buyer: entered, status: 'cancelled' })))).toEqual([]);
    });

    it('warns about a missing e-mail and the disabled auto send', () => {
        const noEmail = invoice({ buyer: buyer({ email: null }) });

        expect(render(noEmail).textContent).toContain('No e-mail.');
        expect(render(noEmail).querySelector('.alert')).toBeNull();

        const warned = render(invoice({ buyer: buyer({ email: null }), auto_send: true }));
        expect(warned.querySelector('.alert')?.textContent).toContain('not sent automatically');
        expect(render(invoice()).textContent).not.toContain('No e-mail');
    });

    it('shows the no-customer text without a name', () => {
        const text = render(invoice({ buyer: buyer({ name: null, source: 'entered' }) })).textContent;

        expect(text).toContain('No customer');
    });
});
