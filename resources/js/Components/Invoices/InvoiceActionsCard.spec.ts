import { describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import InvoiceActionsCard from './InvoiceActionsCard.vue';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { can: { editInvoices: true } } }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}));

const messages = {
    en: { invoice_action_send_email: 'Send by e-mail', invoice_send_requires_email: 'E-mail required' },
};

function render(customerEmail: string | null, recipientChoosable: boolean): HTMLElement {
    const host = document.createElement('div');
    const invoice = { id: 'i1', status: 'issued', customer_email: customerEmail, is_credit_note: false };
    createApp({
        render: () =>
            h(InvoiceActionsCard, {
                invoice: invoice as App.Data.Invoices.InvoiceDetailData,
                recipientChoosable,
            }),
    })
        .use(createI18n({ legacy: false, locale: 'en', messages, missingWarn: false, fallbackWarn: false }))
        .mount(host);
    return host;
}

function sendButton(host: HTMLElement): HTMLElement & { disabled: boolean } {
    return [...host.querySelectorAll('button')].find((b) =>
        b.textContent?.includes('Send by e-mail'),
    ) as HTMLElement & { disabled: boolean };
}

describe('InvoiceActionsCard send', () => {
    it('is enabled without a customer e-mail when the recipient is choosable', () => {
        const host = render(null, true);

        expect(sendButton(host).disabled).toBe(false);
        expect(host.textContent).not.toContain('E-mail required');
    });

    it('is enabled with a customer e-mail', () => {
        expect(sendButton(render('a@b.sk', false)).disabled).toBe(false);
    });

    it('is disabled with a hint when there is neither', () => {
        const host = render(null, false);

        expect(sendButton(host).disabled).toBe(true);
        expect(host.textContent).toContain('E-mail required');
    });
});
