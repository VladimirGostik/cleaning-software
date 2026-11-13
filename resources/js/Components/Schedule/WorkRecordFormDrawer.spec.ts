import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, type Component } from 'vue';
import { createI18n } from 'vue-i18n';

type Submit = { method: string; url: string; payload: unknown };

const sent = vi.hoisted(() => [] as Submit[]);
const formErrors = vi.hoisted(() => ({ value: {} as Record<string, string> }));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        useForm: (initial: Record<string, unknown>) => {
            let stored: Record<string, unknown> = {};
            let convert: (data: Record<string, unknown>) => unknown = (data) => data;
            const form = reactive({
                ...initial,
                errors: formErrors.value,
                processing: false,
                transform: (fn: typeof convert) => {
                    convert = fn;
                },
                defaults: (data: Record<string, unknown>) => {
                    stored = data;
                    return form;
                },
                resetAndClearErrors: () => Object.assign(form, stored),
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
        props: ['open', 'title'],
        setup:
            (props: { open: boolean }, { slots }: { slots: { default?: () => unknown } }) =>
            () =>
                props.open ? h('div', slots.default?.() as never) : null,
    },
}));

import WorkRecordFormDrawer from './WorkRecordFormDrawer.vue';

const record: App.Data.Schedule.JobWorkRecordData = {
    job_id: 'j1',
    performed_at: '2026-11-03T10:30:00.000Z',
    tenant_membership_id: 'm1',
    recorded_by_name: 'Anna',
    note: 'Dvere',
    items: [
        { id: 'a', name: 'Vstup', unit_price: '10.00', object_visit_item_id: 'src-a' },
        { id: 'b', name: 'Schody', unit_price: '5.00', object_visit_item_id: null },
    ],
    photos: [],
    can_update: true,
    can_delete: true,
};

const members = [
    { id: 'm1', label: 'Anna', is_active: true },
    { id: 'm2', label: 'Eva', is_active: true },
];

const items = [{ id: 'x', name: 'Okná', price: '20.00', position: 0 }];

function mount(props: Record<string, unknown>): { host: HTMLElement; close: ReturnType<typeof vi.fn> } {
    const host = document.createElement('div');
    document.body.appendChild(host);
    const close = vi.fn();
    createApp({
        render: () =>
            h(WorkRecordFormDrawer as Component, {
                open: true,
                objectId: 'o1',
                items,
                members,
                onClose: close,
                ...props,
            }),
    })
        .use(createI18n({ legacy: false, locale: 'en', messages: { en: {} }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    return { host, close };
}

const submitButton = (host: HTMLElement) =>
    host.querySelector('button[type="submit"]') as HTMLElement & { disabled: boolean };
const checkbox = (host: HTMLElement, label: string) =>
    [...host.querySelectorAll('label')]
        .find((l) => l.textContent?.includes(label))
        ?.querySelector('input') as HTMLInputElement;

beforeEach(() => {
    formErrors.value = {};
    sent.length = 0;
    document.body.innerHTML = '';
});

describe('WorkRecordFormDrawer add mode', () => {
    it('keeps save disabled until an employee and an item are chosen', async () => {
        const { host } = mount({});

        expect(submitButton(host).disabled).toBe(true);

        const select = host.querySelector('select') as HTMLSelectElement;
        select.value = 'm2';
        select.dispatchEvent(new Event('change'));
        checkbox(host, 'Okná').click();
        await nextTick();

        expect(submitButton(host).disabled).toBe(false);
    });

    it('posts the admin payload to the object route and closes', async () => {
        const { host, close } = mount({});

        const select = host.querySelector('select') as HTMLSelectElement;
        select.value = 'm2';
        select.dispatchEvent(new Event('change'));
        checkbox(host, 'Okná').click();
        await nextTick();
        host.querySelector('form')!.dispatchEvent(new Event('submit'));

        expect(sent).toHaveLength(1);
        expect(sent[0]).toMatchObject({
            method: 'post',
            url: '/objects/o1/work-records',
            payload: { tenant_membership_id: 'm2', item_ids: ['x'], note: null },
        });
        expect(close).toHaveBeenCalled();
    });
});

describe('WorkRecordFormDrawer correct mode', () => {
    it('starts with every stored item kept and the stored price on its label', () => {
        const { host } = mount({ record });

        expect(checkbox(host, 'Vstup').checked).toBe(true);
        expect(checkbox(host, 'Vstup').closest('label')?.textContent).toContain('10');
        expect(checkbox(host, 'Schody').checked).toBe(true);
    });

    it('puts removed and added items in the correction payload', async () => {
        const { host } = mount({ record });

        checkbox(host, 'Schody').click();
        checkbox(host, 'Okná').click();
        await nextTick();
        host.querySelector('form')!.dispatchEvent(new Event('submit'));

        expect(sent[0]).toMatchObject({
            method: 'put',
            url: '/jobs/j1/work-record',
            payload: {
                tenant_membership_id: 'm1',
                performed_at: record.performed_at,
                note: 'Dvere',
                keep_item_ids: ['a'],
                add_item_ids: ['x'],
            },
        });
    });

    it('disables save when every item is removed', async () => {
        const { host } = mount({ record, items: [] });

        checkbox(host, 'Vstup').click();
        await nextTick();
        checkbox(host, 'Schody').click();
        await nextTick();

        expect(submitButton(host).disabled).toBe(true);
    });

    it('shows the stored employee read-only when no member options arrive', () => {
        const { host } = mount({ record, members: [] });

        expect(host.querySelector('select')).toBeNull();
        expect(host.textContent).toContain('Anna');
        expect(host.textContent).not.toContain('work_record_employee_required');
    });

    it('explains a record without any employee instead of leaving a silent disabled save', () => {
        const { host } = mount({
            record: { ...record, tenant_membership_id: null, recorded_by_name: null },
            members: [],
        });

        expect(host.querySelector('select')).not.toBeNull();
        expect(host.textContent).toContain('work_record_employee_required');
        expect(submitButton(host).disabled).toBe(true);
    });

    it('shows an employee error in the read-only state', () => {
        formErrors.value = { tenant_membership_id: 'Neplatný zamestnanec' };

        expect(mount({ record, members: [] }).host.textContent).toContain('Neplatný zamestnanec');
    });

    it('does not offer items that are already in the record', () => {
        const withSource = { ...record, items: [{ ...record.items[0], object_visit_item_id: 'x' }] };
        const { host } = mount({ record: withSource });

        expect(checkbox(host, 'Okná')).toBeUndefined();
        expect(host.textContent).not.toContain('work_record_add_items');
    });

    it('offers the remaining items under the add group', () => {
        const { host } = mount({ record });

        expect(checkbox(host, 'Okná')).toBeDefined();
        expect(host.textContent).toContain('work_record_add_items');
    });

    it('renders item labels as typed, without capitalising', () => {
        const { host } = mount({ record, items: [{ id: 'z', name: 'odhŕňanie snehu', price: '1.00', position: 0 }] });
        const label = checkbox(host, 'odhŕňanie snehu').closest('label')!.querySelector('span')!;

        expect(label.classList.contains('capitalize')).toBe(false);
    });

    it('surfaces an indexed item error', () => {
        formErrors.value = { 'add_item_ids.0': 'Položka už je v zázname' };

        expect(mount({ record }).host.textContent).toContain('Položka už je v zázname');
    });
});
