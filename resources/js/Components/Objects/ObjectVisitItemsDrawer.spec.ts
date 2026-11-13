import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, type Component } from 'vue';
import { createI18n } from 'vue-i18n';

type Submit = { method: string; url: string; payload: unknown };

const sent = vi.hoisted(() => [] as Submit[]);

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        useForm: (initial: Record<string, unknown>) => {
            let convert: (data: Record<string, unknown>) => unknown = (data) => data;
            const form = reactive({
                ...initial,
                errors: { 'items.0.name': 'Zle', 'items.1.id': 'Položka už neexistuje' } as Record<string, string>,
                processing: false,
                transform: (fn: typeof convert) => {
                    convert = fn;
                },
                clearErrors: () => undefined,
                submit: (method: string, url: string, options: { onSuccess: () => void }) => {
                    sent.push({ method, url, payload: convert(form as unknown as Record<string, unknown>) });
                    options.onSuccess();
                },
            });

            return form;
        },
    };
});

vi.mock('@/Components/SideDrawer.vue', () => ({
    default: {
        props: ['open'],
        setup:
            (props: { open: boolean }, { slots }: { slots: { default?: () => unknown } }) =>
            () =>
                props.open ? h('div', slots.default?.() as never) : null,
    },
}));

import ObjectVisitItemsDrawer from './ObjectVisitItemsDrawer.vue';

const items = [
    { id: 'i1', name: 'Vstup', price: '10.00', position: 0 },
    { id: 'i2', name: 'Schody', price: '5.50', position: 1 },
];

function mount(): { host: HTMLElement; close: ReturnType<typeof vi.fn> } {
    const host = document.createElement('div');
    document.body.appendChild(host);
    const close = vi.fn();
    createApp({
        render: () => h(ObjectVisitItemsDrawer as Component, { open: true, objectId: 'o1', items, onClose: close }),
    })
        .use(createI18n({ legacy: false, locale: 'en', messages: { en: {} }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    return { host, close };
}

const button = (host: HTMLElement, label: string, index = 0) =>
    host.querySelectorAll(`button[aria-label="${label}"]`)[index] as HTMLElement & { disabled: boolean };

beforeEach(() => {
    sent.length = 0;
    document.body.innerHTML = '';
});

describe('ObjectVisitItemsDrawer', () => {
    it('prefills a row per item and shows the row error', () => {
        const { host } = mount();

        expect(host.querySelectorAll('li input[type="text"]')).toHaveLength(2);
        expect((host.querySelector('input[type="number"]') as HTMLInputElement).value).toBe('10');
        expect(host.textContent).toContain('Zle');
    });

    it('gives every input a visible label, a row-numbered accessible name and the unit', () => {
        const { host } = mount();

        expect(host.textContent).toContain('object_visit_item_name');
        expect(host.querySelector('input[aria-label="object_visit_item_name_row"]')).not.toBeNull();
        expect(host.querySelector('input[aria-label="object_visit_item_price_row"]')).not.toBeNull();
        expect(host.textContent).toContain('€');
    });

    it('shows a server error about a row id', () => {
        expect(mount().host.textContent).toContain('Položka už neexistuje');
    });

    it('focuses the name of a new row', async () => {
        const { host } = mount();

        [...host.querySelectorAll('button')].find((b) => b.textContent?.includes('object_visit_item_add'))!.click();
        await nextTick();
        await nextTick();

        const names = host.querySelectorAll<HTMLInputElement>('li input[type="text"]');
        expect(document.activeElement).toBe(names[names.length - 1]);
    });

    it('moves focus to the neighbouring row after a removal and to the add button when empty', async () => {
        const { host } = mount();

        button(host, 'object_visit_item_remove', 0).click();
        await nextTick();
        await nextTick();
        expect(document.activeElement).toBe(host.querySelector('li input[type="text"]'));

        button(host, 'object_visit_item_remove', 0).click();
        await nextTick();
        await nextTick();
        expect(document.activeElement?.textContent).toContain('object_visit_item_add');
    });

    it('puts the rows in the new order and drops removed ones when saving', async () => {
        const { host, close } = mount();

        button(host, 'object_visit_item_move_up', 1).click();
        await nextTick();
        button(host, 'object_visit_item_remove', 1).click();
        await nextTick();
        host.querySelector('form')!.dispatchEvent(new Event('submit'));

        expect(sent[0]).toEqual({
            method: 'put',
            url: '/objects/o1/visit-items',
            payload: { items: [{ id: 'i2', name: 'Schody', price: 5.5 }] },
        });
        expect(close).toHaveBeenCalled();
    });

    it('adds an empty row without an id', async () => {
        const { host } = mount();

        [...host.querySelectorAll('button')].find((b) => b.textContent?.includes('object_visit_item_add'))!.click();
        await nextTick();
        host.querySelector('form')!.dispatchEvent(new Event('submit'));

        expect((sent[0].payload as { items: unknown[] }).items[2]).toEqual({ id: null, name: '', price: '' });
    });
});
