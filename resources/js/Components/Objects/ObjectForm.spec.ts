import { describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import { createI18n } from 'vue-i18n';
import ObjectForm from './ObjectForm.vue';

const mockErrors = vi.hoisted(() => ({}) as Record<string, string>);

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        useForm: (_m: string, _u: string, data: Record<string, unknown>) =>
            reactive({ ...data, errors: mockErrors, processing: false, submit: () => undefined }),
        usePage: () => ({ props: { can: { viewAllObjects: true } } }),
    };
});

vi.mock('@/Components/Contacts/ContactsListField.vue', () => ({ default: { render: () => null } }));

function render(focusSection?: 'billing', object: unknown = null): HTMLElement {
    const host = document.createElement('div');
    createApp({
        render: () => h(ObjectForm, { object: object as never, clients: [], focusSection }),
    })
        .use(createI18n({ legacy: false, locale: 'en', messages: { en: {} }, missingWarn: false, fallbackWarn: false }))
        .mount(host);
    return host;
}

const field = (host: HTMLElement, label: string) =>
    [...host.querySelectorAll('fieldset')]
        .find((f) => f.querySelector('legend')?.textContent?.includes(label))
        ?.querySelector('input');

const editObject = (name: string) => ({
    id: 'o1',
    client_id: 'c1',
    type: 'office',
    name: 'Obj',
    country: 'SK',
    is_active: true,
    key_count: null,
    area_sqm: null,
    floor: null,
    contacts: [],
    billing: { name, email: null },
});

describe('ObjectForm billing focus', () => {
    it('autofocuses billing_name for the billing section only', () => {
        expect(field(render('billing'), 'object_billing_name')?.hasAttribute('autofocus')).toBe(true);
        expect(field(render(), 'object_billing_name')?.hasAttribute('autofocus')).toBe(false);
    });

    it('renders the billing e-mail input', () => {
        expect(field(render(), 'object_billing_email')?.getAttribute('type')).toBe('email');
    });
});

describe('ObjectForm layout', () => {
    it('renders the sections in order', () => {
        const headings = [...render().querySelectorAll('h2')].map((h) => h.textContent?.trim());

        expect(headings).toEqual([
            'basic_info',
            'object_billing',
            'object_contacts',
            'object_address',
            'object_more_details',
        ]);
    });

    it('places the billing e-mail last in billing, before the contacts section', () => {
        const host = render();
        const email = field(host, 'object_billing_email')!;
        const postal = field(host, 'postal_code')!;
        const contactsHeading = [...host.querySelectorAll('h2')].find((h) =>
            h.textContent?.includes('object_contacts'),
        )!;

        expect(postal.compareDocumentPosition(email) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(email.compareDocumentPosition(contactsHeading) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('keeps the extra details last, right before the active toggle', () => {
        const host = render(undefined, editObject(''));
        const toggle = host.querySelector('input[type="checkbox"]')!;
        const more = host.querySelector('[aria-controls]')!;

        expect(more.compareDocumentPosition(toggle) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('hides the extra fields until the toggle is clicked', async () => {
        const host = render();
        const toggle = host.querySelector('button[aria-controls]') as HTMLElement;
        const panel = host.querySelector(`[id="${toggle.getAttribute('aria-controls')}"]`) as HTMLElement;

        expect(toggle.getAttribute('aria-expanded')).toBe('false');
        expect(panel.style.display).toBe('none');
        expect(panel.querySelectorAll('input')).toHaveLength(6);

        toggle.click();
        await nextTick();

        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        expect(panel.style.display).not.toBe('none');
    });

    it.each(['area_sqm', 'floor', 'access_code', 'key_box_code', 'key_count', 'billing_peppol_participant_id'])(
        'opens the extra details when %s has an error',
        (name) => {
            mockErrors[name] = 'bad';
            const host = render();
            delete mockErrors[name];

            expect(host.querySelector('button[aria-controls]')!.getAttribute('aria-expanded')).toBe('true');
        },
    );

    it('keeps the Peppol ID out of billing, last in the collapsed extra details', async () => {
        const host = render();
        const toggle = host.querySelector('button[aria-controls]') as HTMLElement;
        const panel = host.querySelector(`[id="${toggle.getAttribute('aria-controls')}"]`) as HTMLElement;
        const peppol = field(host, 'peppol_participant_id')!;
        const inputs = [...panel.querySelectorAll('input')];

        expect(panel.contains(peppol)).toBe(true);
        expect(inputs[inputs.length - 1]).toBe(peppol);
        expect(panel.style.display).toBe('none');

        toggle.click();
        await nextTick();

        expect(panel.style.display).not.toBe('none');
    });

    it('leaves name without autofocus for the billing deep link', () => {
        const name = (host: HTMLElement) => field(host, 'object_name')!;

        expect(name(render('billing')).hasAttribute('autofocus')).toBe(false);
        expect(name(render()).hasAttribute('autofocus')).toBe(true);
    });

    it('marks the billing address required only once billing_name is filled', () => {
        const object = editObject;

        for (const label of ['street', 'city', 'postal_code']) {
            const inputs = (host: HTMLElement) =>
                [...host.querySelectorAll('fieldset')]
                    .filter((f) => f.querySelector('legend')?.textContent?.includes(label))
                    .map((f) => f.querySelector('input')!);

            expect(inputs(render(undefined, object('Dom s.r.o.')))[0].hasAttribute('required')).toBe(true);
            expect(inputs(render(undefined, object('')))[0].hasAttribute('required')).toBe(false);
        }
    });
});
