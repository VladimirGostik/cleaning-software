import { describe, expect, it, vi } from 'vitest';
import { createApp, h } from 'vue';
import { createI18n } from 'vue-i18n';
import ObjectForm from './ObjectForm.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        useForm: (_m: string, _u: string, data: Record<string, unknown>) =>
            reactive({ ...data, errors: {}, processing: false, submit: () => undefined }),
        usePage: () => ({ props: { can: { viewAllObjects: true } } }),
    };
});

vi.mock('@/Components/Contacts/ContactsListField.vue', () => ({ default: { render: () => null } }));

function render(focusSection?: 'billing'): HTMLElement {
    const host = document.createElement('div');
    createApp({
        render: () => h(ObjectForm, { object: null, clients: [], focusSection }),
    })
        .use(createI18n({ legacy: false, locale: 'en', messages: { en: {} }, missingWarn: false, fallbackWarn: false }))
        .mount(host);
    return host;
}

const field = (host: HTMLElement, label: string) =>
    [...host.querySelectorAll('fieldset')]
        .find((f) => f.querySelector('legend')?.textContent?.includes(label))
        ?.querySelector('input');

describe('ObjectForm billing focus', () => {
    it('autofocuses billing_name for the billing section only', () => {
        expect(field(render('billing'), 'object_billing_name')?.hasAttribute('autofocus')).toBe(true);
        expect(field(render(), 'object_billing_name')?.hasAttribute('autofocus')).toBe(false);
    });

    it('renders the billing e-mail input', () => {
        expect(field(render(), 'object_billing_email')?.getAttribute('type')).toBe('email');
    });
});
