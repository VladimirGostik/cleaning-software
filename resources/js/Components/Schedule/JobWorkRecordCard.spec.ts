import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, type Component } from 'vue';
import { createI18n } from 'vue-i18n';

const drawerProps = vi.hoisted(() => ({ value: null as Record<string, unknown> | null }));

vi.mock('@inertiajs/vue3', () => ({ router: { delete: vi.fn() } }));
vi.mock('./WorkRecordFormDrawer.vue', () => ({
    default: {
        props: ['open', 'objectId', 'record', 'items', 'members'],
        setup: (props: Record<string, unknown>) => () => {
            drawerProps.value = props;
            return h('div', { id: 'drawer', 'data-open': String(props.open) });
        },
    },
}));
vi.mock('@/Components/ConfirmDeleteModal.vue', () => ({ default: { render: () => null } }));

import JobWorkRecordCard from './JobWorkRecordCard.vue';

const record = (patch: Partial<App.Data.Schedule.JobWorkRecordData> = {}): App.Data.Schedule.JobWorkRecordData => ({
    job_id: 'j1',
    performed_at: '2026-11-03T10:30:00.000Z',
    tenant_membership_id: 'm1',
    recorded_by_name: 'Anna',
    note: 'Dvere',
    items: [
        { id: 'a', name: 'Vstup', unit_price: '10.00', object_visit_item_id: 'x' },
        { id: 'b', name: 'Schody', unit_price: null, object_visit_item_id: null },
    ],
    photos: [{ id: 'p1', name: 'a.jpg', url: '/jobs/j1/photos/p1' }],
    can_update: true,
    can_delete: true,
    ...patch,
});

const items = [{ id: 'y', name: 'Okná', price: '20.00', position: 0 }];

function mount(data: App.Data.Schedule.JobWorkRecordData): HTMLElement {
    const host = document.createElement('div');
    document.body.appendChild(host);
    createApp({
        render: () => h(JobWorkRecordCard as Component, { record: data, objectId: 'o1', items, members: [] }),
    })
        .use(createI18n({ legacy: false, locale: 'en', messages: { en: {} }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    return host;
}

beforeEach(() => {
    drawerProps.value = null;
    document.body.innerHTML = '';
});

describe('JobWorkRecordCard', () => {
    it('renders the items, note, person and photo link', () => {
        const host = mount(record());

        expect(host.textContent).toContain('Vstup');
        expect(host.textContent).toContain('Dvere');
        expect(host.textContent).toContain('Anna');
        expect(host.querySelector('a[href="/jobs/j1/photos/p1"]')).not.toBeNull();
    });

    it('shows a price only for items that carry one', () => {
        const rows = [...mount(record()).querySelectorAll('ul li')].filter((li) => li.textContent?.includes('Schody'));

        expect(rows[0].querySelectorAll('span')).toHaveLength(1);
        expect(mount(record()).textContent).toMatch(/10[.,]00/);
    });

    it('passes the object items on to the correction drawer', () => {
        mount(record());

        expect(drawerProps.value?.items).toEqual(items);
        expect(drawerProps.value?.objectId).toBe('o1');
    });

    it('hides edit and delete when the flags are off', () => {
        const host = mount(record({ can_update: false, can_delete: false }));

        expect(host.querySelectorAll('button')).toHaveLength(0);
    });
});
