import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, type Component } from 'vue';
import { createI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';

vi.mock('@inertiajs/vue3', () => ({ router: { reload: vi.fn() } }));
vi.mock('@/Components/Schedule/WorkRecordFormDrawer.vue', () => ({
    default: {
        props: ['open', 'record'],
        setup: (props: { open: boolean; record: { job_id: string } | null }) => () =>
            h('div', { id: 'drawer', 'data-open': String(props.open), 'data-record': props.record?.job_id ?? '' }),
    },
}));
vi.mock('@/Components/ConfirmDeleteModal.vue', () => ({
    default: {
        props: ['isOpen'],
        setup: (props: { isOpen: boolean }) => () => h('div', { id: 'confirm', 'data-open': String(props.isOpen) }),
    },
}));

import ObjectVisitRecordsCard from './ObjectVisitRecordsCard.vue';

const record = (
    id: string,
    flags: { can_update: boolean; can_delete: boolean },
): App.Data.Schedule.JobWorkRecordData => ({
    job_id: id,
    performed_at: '2026-10-03T10:30:00.000Z',
    tenant_membership_id: 'm1',
    recorded_by_name: 'Anna',
    note: 'Dvere',
    items: [{ id: `${id}-i`, name: 'Vstup', unit_price: '10.00', object_visit_item_id: null }],
    photos: [{ id: 'p1', name: 'a.jpg', url: '/jobs/j1/photos/p1' }],
    ...flags,
});

const overview = (
    patch: Partial<App.Data.Objects.ObjectVisitRecordsData> = {},
): App.Data.Objects.ObjectVisitRecordsData => ({
    month: '2026-10',
    summary: [
        { name: 'Vstup', unit_price: '10.00', count: 2, total: '20.00' },
        { name: 'Vstup', unit_price: '12.00', count: 1, total: '12.00' },
    ],
    total: '32.00',
    records: [
        record('j1', { can_update: true, can_delete: true }),
        record('j2', { can_update: false, can_delete: false }),
    ],
    can_add: true,
    members: [],
    ...patch,
});

const liveItems = [{ id: 'i1', name: 'Vstup', price: '10.00', position: 0 }];

function mount(
    data: App.Data.Objects.ObjectVisitRecordsData,
    items: App.Data.Objects.ObjectVisitItemData[] = liveItems,
): HTMLElement {
    const host = document.createElement('div');
    document.body.appendChild(host);
    createApp({
        render: () => h(ObjectVisitRecordsCard as Component, { objectId: 'o1', overview: data, items }),
    })
        .use(createI18n({ legacy: false, locale: 'en', messages: { en: {} }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    return host;
}

const byLabel = (host: HTMLElement, label: string) =>
    host.querySelector(`button[aria-label="${label}"]`) as HTMLElement & { disabled: boolean };

beforeEach(() => {
    vi.mocked(router.reload).mockReset();
    document.body.innerHTML = '';
});

describe('ObjectVisitRecordsCard', () => {
    it('shows one summary line per name and price plus the total', () => {
        const text = mount(overview()).textContent ?? '';

        expect(text).toMatch(/2×/);
        expect(text).toMatch(/1×/);
        expect(text).toContain('work_records_total');
        expect(text).toMatch(/32[.,]00/);
    });

    it('reloads only visitRecords for the previous month', () => {
        byLabel(mount(overview()), 'work_records_month_previous').click();

        expect(router.reload).toHaveBeenCalledWith(
            expect.objectContaining({ only: ['visitRecords'], data: { records_month: '2026-09' } }),
        );
    });

    it('disables next on the current month and enables it before', () => {
        const now = new Date();
        const current = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

        expect(byLabel(mount(overview({ month: current })), 'work_records_month_next').disabled).toBe(true);
        expect(byLabel(mount(overview({ month: '2020-01' })), 'work_records_month_next').disabled).toBe(false);
    });

    it('shows edit and delete only for records whose flags allow them', () => {
        const host = mount(overview());

        expect(host.querySelectorAll('button[aria-label="work_record_edit_title"]')).toHaveLength(1);
        expect(host.querySelectorAll('button[aria-label="work_record_delete"]')).toHaveLength(1);
    });

    it('opens the drawer for a new record and for a correction', async () => {
        const host = mount(overview());
        const drawer = () => host.querySelector('#drawer') as HTMLElement;

        const add = [...host.querySelectorAll('button')].find((b) => b.textContent?.includes('work_records_add'));
        add!.click();
        await nextTick();
        expect(drawer().dataset.open).toBe('true');
        expect(drawer().dataset.record ?? '').toBe('');

        byLabel(host, 'work_record_edit_title').click();
        await nextTick();
        expect(drawer().dataset.record).toBe('j1');
    });

    it('hides the add button without can_add and shows the empty state without records', () => {
        const host = mount(overview({ can_add: false, records: [], summary: [] }));

        expect([...host.querySelectorAll('button')].some((b) => b.textContent?.includes('work_records_add'))).toBe(
            false,
        );
        expect(host.textContent).toContain('work_records_empty');
    });

    it('replaces the add button with a hint when the object has no per-visit items', () => {
        const host = mount(overview(), []);

        expect([...host.querySelectorAll('button')].some((b) => b.textContent?.includes('work_records_add'))).toBe(
            false,
        );
        expect(host.textContent).toContain('work_records_define_items');
    });

    it('lists photo links with accessible names', () => {
        const link = mount(overview()).querySelector('a[href="/jobs/j1/photos/p1"]');

        expect(link?.getAttribute('aria-label')).toBe('work_record_photo_open');
    });
});
