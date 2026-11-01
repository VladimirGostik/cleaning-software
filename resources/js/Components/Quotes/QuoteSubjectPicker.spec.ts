import { describe, expect, it } from 'vitest';
import { createApp, defineComponent, h, reactive } from 'vue';
import { createI18n } from 'vue-i18n';
import { provideForm } from '@/Components/Forms/useFormContext';
import QuoteSubjectPicker, { type QuoteSubjectMode } from './QuoteSubjectPicker.vue';

const MESSAGE = 'Vyberte klienta alebo vyplňte názov odberateľa.';

function render(mode: QuoteSubjectMode): HTMLElement {
    const form = reactive({
        client_id: null,
        cleaning_object_id: null,
        customer_name: null,
        customer_email: null,
        customer_street: null,
        customer_city: null,
        customer_postal_code: null,
        errors: { customer_name: MESSAGE },
    });
    const Host = defineComponent({
        setup() {
            provideForm(form as never);
            return () => h(QuoteSubjectPicker, { mode, clients: [], objects: [] });
        },
    });
    const host = document.createElement('div');
    createApp(Host)
        .use(createI18n({ legacy: false, locale: 'sk', missingWarn: false, fallbackWarn: false }))
        .mount(host);
    return host;
}

function count(host: HTMLElement): number {
    return host.textContent!.split(MESSAGE).length - 1;
}

describe('QuoteSubjectPicker customer_name error', () => {
    it('shows the message under the client select in client mode', () => {
        const host = render('client');
        const select = host.querySelector('select')!;
        const error = host.querySelector('p.text-error')!;
        expect(error.textContent?.trim()).toBe(MESSAGE);
        expect(select.compareDocumentPosition(error) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('shows the message once in manual mode', () => {
        expect(count(render('manual'))).toBe(1);
    });
});
