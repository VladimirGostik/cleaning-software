import { describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import QuoteRecurringInvoiceAction from './QuoteRecurringInvoiceAction.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}));

function render(periods: App.Enums.QuoteBillingPeriodEnum[]): HTMLElement {
    const host = document.createElement('div');
    createApp({ render: () => h(QuoteRecurringInvoiceAction, { quoteId: 'q-1', periods }) })
        .use(createI18n({ legacy: false, locale: 'sk', missingWarn: false, fallbackWarn: false }))
        .mount(host);
    return host;
}

describe('QuoteRecurringInvoiceAction', () => {
    it('renders a single link for one period', () => {
        const links = render(['monthly']).querySelectorAll('a');

        expect(links).toHaveLength(1);
        expect(links[0].getAttribute('href')).toBe('/recurring-invoices/create?quote_id=q-1&billing_period=monthly');
    });

    it('renders a dropdown with one link per period', () => {
        const host = render(['monthly', 'yearly']);
        const links = [...host.querySelectorAll('a')];

        expect(host.querySelector('.dropdown')).not.toBeNull();
        expect(links.map((a) => a.getAttribute('href'))).toEqual([
            '/recurring-invoices/create?quote_id=q-1&billing_period=monthly',
            '/recurring-invoices/create?quote_id=q-1&billing_period=yearly',
        ]);
    });

    it('renders nothing without periods', () => {
        expect(render([]).querySelectorAll('a')).toHaveLength(0);
    });
});
