import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h, nextTick, reactive, ref } from 'vue';
import { createI18n } from 'vue-i18n';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import InvoiceSubjectPicker, { type SubjectMode } from './InvoiceSubjectPicker.vue';

const state = vi.hoisted(() => ({
    previewRef: null as null | { value: unknown },
    preview: null as unknown,
    form: null as unknown,
}));

vi.mock('@/Composables/useBuyerPreview', async () => {
    const { ref: r } = await import('vue');
    return {
        useBuyerPreview: () => ({
            preview: (state.previewRef = r(state.preview)),
            loading: r(false),
            error: r(false),
        }),
    };
});

const messages = {
    en: {
        type: 'Type',
        client: 'Client',
        select_client: 'Select client',
        invoice_select_object: 'Object',
        invoice_subject_mode_client: 'Client',
        invoice_subject_mode_object: 'Object',
        invoice_subject_mode_standalone: 'Standalone',
        invoice_buyer_preview_title: 'Buyer on invoice',
        invoice_pdf_ico: 'IČO',
        invoice_customer_representative: 'Representative',
        invoice_customer_representative_placeholder: '',
        invoice_customer_email_hint: 'Hint',
        email: 'E-mail',
        invoice_customer_name: 'Name',
    },
};

const buyer = {
    source: 'client',
    name: 'Klient s.r.o.',
    ico: '999',
    email: null,
} as App.Data.Invoices.InvoiceBuyerData;

function render(initialMode: SubjectMode, formData: Record<string, unknown>) {
    const host = document.createElement('div');
    const mode = ref<SubjectMode>(initialMode);
    const form = reactive({
        errors: {},
        client_id: 'c1',
        cleaning_object_id: null,
        customer_name: null,
        customer_representative: null,
        customer_email: null,
        ...formData,
    });
    state.form = form;
    const Root = defineComponent({
        setup() {
            return () =>
                h(FormProvider, { form: form as unknown as InstanceType<typeof FormProvider>['$props']['form'] }, () =>
                    h(InvoiceSubjectPicker, {
                        mode: mode.value,
                        clients: [{ id: 'c1', name: 'Klient s.r.o. ' }] as App.Data.Clients.ClientOptionData[],
                        objects: [],
                        'onUpdate:mode': (m: SubjectMode) => (mode.value = m),
                    }),
                );
        },
    });
    createApp(Root)
        .use(createI18n({ legacy: false, locale: 'en', messages, missingWarn: false, fallbackWarn: false }))
        .mount(host);
    return { host, mode };
}

describe('InvoiceSubjectPicker', () => {
    beforeEach(() => {
        state.preview = null;
    });

    it('renders the buyer preview in client mode', () => {
        state.preview = { buyer, email_options: ['x@y.sk'] };
        const { host } = render('client', {});

        expect(host.textContent).toContain('Buyer on invoice');
        expect(host.textContent).toContain('Klient s.r.o.');
    });

    it('shows e-mail and representative in every mode, with suggestions and no default', () => {
        state.preview = { buyer, email_options: ['x@y.sk', 'z@y.sk'] };

        for (const mode of ['client', 'object', 'standalone'] as const) {
            const { host } = render(mode, {});
            const email = host.querySelector('input[type="email"]') as HTMLInputElement;

            expect(email).not.toBeNull();
            expect(email.value).toBe('');
            const list = host.querySelector(`datalist[id="${email.getAttribute('list')}"]`);
            expect([...(list?.querySelectorAll('option') ?? [])].map((o) => o.getAttribute('value'))).toEqual([
                'x@y.sk',
                'z@y.sk',
            ]);
            expect(host.textContent).toContain('Representative');
        }
    });

    it('replaces a prefilled e-mail when another subject is picked and resets it on clear', async () => {
        state.preview = { buyer: { ...buyer, email: 'a@x.sk' }, email_options: [] };
        render('client', {});
        const form = state.form as Record<string, unknown>;
        expect(form.customer_email).toBe('a@x.sk');

        state.previewRef!.value = { buyer: { ...buyer, email: 'b@x.sk' }, email_options: [] };
        await nextTick();
        expect(form.customer_email).toBe('b@x.sk');

        state.previewRef!.value = null;
        await nextTick();
        expect(form.customer_email).toBeNull();
    });

    it('keeps a typed e-mail when the preview changes', async () => {
        state.preview = { buyer: { ...buyer, email: 'a@x.sk' }, email_options: [] };
        render('client', {});
        const form = state.form as Record<string, unknown>;

        form.customer_email = 'typed@x.sk';
        state.previewRef!.value = { buyer: { ...buyer, email: 'b@x.sk' }, email_options: [] };
        await nextTick();
        expect(form.customer_email).toBe('typed@x.sk');

        state.previewRef!.value = null;
        await nextTick();
        expect(form.customer_email).toBe('typed@x.sk');
    });

    it('keeps e-mail and representative across a mode change', async () => {
        const { host, mode } = render('standalone', {
            customer_email: 'keep@me.sk',
            customer_representative: 'Jozef',
        });
        const radio = host.querySelector('input[type="radio"][value="client"]') as HTMLInputElement;

        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
        await Promise.resolve();

        expect(mode.value).toBe('client');
        const form = state.form as Record<string, unknown>;
        expect(form.customer_email).toBe('keep@me.sk');
        expect(form.customer_representative).toBe('Jozef');
    });

    it('prefills an empty e-mail from the buyer and never overwrites a typed one', () => {
        state.preview = { buyer: { ...buyer, email: 'buyer@x.sk' }, email_options: [] };

        render('client', {});
        expect((state.form as Record<string, unknown>).customer_email).toBe('buyer@x.sk');

        render('client', { customer_email: 'typed@x.sk' });
        expect((state.form as Record<string, unknown>).customer_email).toBe('typed@x.sk');
    });
});
