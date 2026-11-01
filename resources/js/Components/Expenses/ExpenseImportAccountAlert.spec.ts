import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, createApp, defineComponent, h, ref } from 'vue';
import { createI18n } from 'vue-i18n';
import sk from '../../../lang/sk/app.json';
import ExpenseImportAccountAlert from './ExpenseImportAccountAlert.vue';

const navigate = vi.fn();
const can = { manageBillingSettings: true };

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (p, { slots }) =>
            () =>
                h('a', { href: p.href }, slots.default?.()),
    }),
}));
vi.mock('@/Composables/usePageProps', () => ({
    usePageProps: () => computed(() => ({ tenant: { active: { name: 'Aktivna s.r.o.' } }, can })),
}));
vi.mock('@/Composables/useTenantSwitchVisit', () => ({
    useTenantSwitchVisit: () => ({ switchingTo: ref<string | null>(null), navigate }),
}));

function render(check: Partial<App.Data.Expenses.ExpenseImportAccountCheckData>): HTMLElement {
    const host = document.createElement('div');
    const full: App.Data.Expenses.ExpenseImportAccountCheckData = {
        status: 'unknown_account',
        statement_iban: 'SK1111111111111111111111',
        owner_tenant_id: null,
        owner_tenant_name: null,
        ...check,
    };
    createApp(defineComponent({ render: () => h(ExpenseImportAccountAlert, { check: full }) }))
        .use(createI18n({ legacy: false, locale: 'sk', messages: { sk }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    return host;
}

describe('ExpenseImportAccountAlert', () => {
    beforeEach(() => {
        navigate.mockReset();
        can.manageBillingSettings = true;
    });

    it('other_company names the firm and switches to the import page', () => {
        const host = render({ status: 'other_company', owner_tenant_id: 't2', owner_tenant_name: 'Druha s.r.o.' });

        expect(host.textContent).toContain('Druha s.r.o.');
        expect(host.textContent).toContain('SK1111111111111111111111');
        host.querySelector('button')!.click();
        expect(navigate).toHaveBeenCalledWith('t2', '/expenses/import');
    });

    it('unknown_account has no button and shows the active firm', () => {
        const host = render({ status: 'unknown_account' });

        expect(host.querySelector('button')).toBeNull();
        expect(host.querySelector('a')).toBeNull();
        expect(host.textContent).toContain('Aktivna s.r.o.');
    });

    it('company_iban_missing links to the settings with the permission', () => {
        const host = render({ status: 'company_iban_missing' });

        expect(host.querySelector('a')!.getAttribute('href')).toBe('/settings/invoicing');
    });

    it('company_iban_missing shows the fallback text without the permission', () => {
        can.manageBillingSettings = false;
        const host = render({ status: 'company_iban_missing' });

        expect(host.querySelector('a')).toBeNull();
        expect(host.textContent).toContain('Požiadajte správcu');
    });
});
