import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h, reactive } from 'vue';
import { createI18n } from 'vue-i18n';
import { provideForm } from '@/Components/Forms/useFormContext';
import QuoteSubjectPicker, { type QuoteSubjectMode } from './QuoteSubjectPicker.vue';

const http = vi.hoisted(() => ({ getJson: vi.fn() }));

vi.mock('@/utils/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/http')>()),
    getJson: http.getJson,
}));

const MESSAGE = 'Vyberte klienta alebo vyplňte názov odberateľa.';

function render(mode: QuoteSubjectMode, clientId: string | null = null): HTMLElement {
    const form = reactive({
        client_id: clientId,
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

beforeEach(() => http.getJson.mockReset());

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

describe('QuoteSubjectPicker buyer preview', () => {
    it('requests the quote preview for a chosen client and renders the buyer', async () => {
        vi.useFakeTimers();
        http.getJson.mockResolvedValue({
            buyer: { source: 'client', name: 'Klient s.r.o.' },
            email_options: [],
        });
        const host = render('client', 'c1');

        await vi.advanceTimersByTimeAsync(400);
        vi.useRealTimers();
        await Promise.resolve();

        expect(http.getJson.mock.calls[0][0]).toBe('/quotes/buyer-preview?client_id=c1&cleaning_object_id=');
        expect(host.textContent).toContain('Klient s.r.o.');
    });

    it('makes no request in manual mode', async () => {
        vi.useFakeTimers();
        render('manual', 'c1');

        await vi.advanceTimersByTimeAsync(400);
        vi.useRealTimers();

        expect(http.getJson).not.toHaveBeenCalled();
    });
});
