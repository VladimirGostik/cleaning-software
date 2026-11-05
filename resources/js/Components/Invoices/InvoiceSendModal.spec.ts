import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import InvoiceSendModal from './InvoiceSendModal.vue';

const state = vi.hoisted(() => ({ form: null as unknown }));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive: r } = await import('vue');
    return {
        useForm: (_method: string, _url: string, data: Record<string, unknown>) => {
            const form = r({
                ...data,
                errors: {} as Record<string, string>,
                processing: false,
                transform() {
                    return form;
                },
                submit: () => undefined,
            });
            state.form = form;
            return form;
        },
        Link: defineComponent({
            setup:
                (_p, { slots }) =>
                () =>
                    h('a', slots.default?.()),
        }),
    };
});

const messages = {
    en: {
        invoice_action_send_email: 'Send',
        invoice_send_recipient: 'Recipient',
        invoice_send_recipient_hint: 'Pick or type.',
        invoice_send_save_to_recurring: 'Save to recurring',
        invoice_send_save_to_object: 'Save to object',
        cancel: 'Cancel',
    },
};

function render(options: App.Data.Invoices.InvoiceSendOptionsData): HTMLElement {
    const host = document.createElement('div');
    createApp({
        render: () => h(InvoiceSendModal, { open: true, invoiceId: 'i1', options }),
    })
        .use(createI18n({ legacy: false, locale: 'en', messages, missingWarn: false, fallbackWarn: false }))
        .mount(host);
    return host;
}

describe('InvoiceSendModal', () => {
    beforeEach(() => {
        state.form = null;
    });

    it('has no preselected recipient and offers the suggestions in a datalist', () => {
        const host = render({ email_options: ['a@x.sk', 'b@x.sk'], save_target: null });
        const input = host.querySelector('input[type="email"]') as HTMLInputElement;

        expect(input.value).toBe('');
        const listId = input.getAttribute('list');
        expect(listId).toBeTruthy();
        const options = [...(host.querySelector(`datalist[id="${listId}"]`)?.querySelectorAll('option') ?? [])];
        expect(options.map((o) => o.getAttribute('value'))).toEqual(['a@x.sk', 'b@x.sk']);
    });

    it('omits the datalist without suggestions', () => {
        const host = render({ email_options: [], save_target: null });

        expect(host.querySelector('datalist')).toBeNull();
        expect(host.querySelector('input[type="email"]')?.hasAttribute('list')).toBe(false);
    });

    it('shows the save checkbox only with a target and labels it per target', () => {
        expect(render({ email_options: [], save_target: null }).querySelector('input[type="checkbox"]')).toBeNull();

        const recurring = render({ email_options: [], save_target: 'recurring_invoice' });
        expect(recurring.querySelector('input[type="checkbox"]')).not.toBeNull();
        expect(recurring.textContent).toContain('Save to recurring');

        expect(render({ email_options: [], save_target: 'object' }).textContent).toContain('Save to object');
    });

    it('shows a 422 error under the recipient field', async () => {
        const host = render({ email_options: [], save_target: null });
        (state.form as { errors: Record<string, string> }).errors.recipient_email = 'Recipient required';
        await nextTick();

        const fieldset = host.querySelector('input[type="email"]')?.closest('fieldset');
        expect(fieldset?.textContent).toContain('Recipient required');
    });
});
