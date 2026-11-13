import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest';
import {
    addableItems,
    hasNextMonth,
    itemsError,
    isFormComplete,
    latestTimeFor,
    moveRow,
    newFormState,
    newVisitItemRow,
    selectedItemCount,
    stateFromRecord,
    toAddPayload,
    toPerformedAt,
    toUpdatePayload,
    toVisitItemsPayload,
} from './workRecords';

const record: App.Data.Schedule.JobWorkRecordData = {
    job_id: 'j1',
    performed_at: new Date(2026, 10, 3, 23, 30).toISOString(),
    tenant_membership_id: 'm1',
    recorded_by_name: 'Anna',
    note: 'Dvere',
    items: [
        { id: 'a', name: 'Vstup', unit_price: '10.00', object_visit_item_id: 'src-a' },
        { id: 'b', name: 'Schody', unit_price: null, object_visit_item_id: null },
    ],
    photos: [],
    can_update: true,
    can_delete: true,
};

describe('month maths', () => {
    it('allows next only before the current month', () => {
        const today = new Date(2026, 10, 13);

        expect(hasNextMonth('2026-10', today)).toBe(true);
        expect(hasNextMonth('2026-11', today)).toBe(false);
    });
});

describe('performed_at', () => {
    it('keeps the local day for a late evening time', () => {
        const state = stateFromRecord(record);

        expect(state.date).toBe('2026-11-03');
        expect(state.time).toBe('23:30');
        expect(toPerformedAt(state.date, state.time)).toBe(record.performed_at);
    });

    it('returns null for an incomplete or invalid value', () => {
        expect(toPerformedAt(null, '10:00')).toBeNull();
        expect(toPerformedAt('2026-11-03', null)).toBeNull();
        expect(toPerformedAt('2026-13-45', '10:00')).toBeNull();
    });

    it('caps the time only on the current day', () => {
        const now = new Date(2026, 10, 13, 9, 5);

        expect(latestTimeFor('2026-11-13', now)).toBe('09:05');
        expect(latestTimeFor('2026-11-12', now)).toBeUndefined();
        expect(latestTimeFor(null, now)).toBeUndefined();
    });
});

describe('performed_at in a non-UTC zone', () => {
    beforeAll(() => {
        vi.stubEnv('TZ', 'Europe/Bratislava');
    });

    afterAll(() => {
        vi.unstubAllEnvs();
    });

    it('really runs outside UTC', () => {
        expect(new Date('2026-11-03T12:00:00Z').getHours()).toBe(13);
    });

    it('converts a late-evening local time to the same UTC day minus one hour', () => {
        expect(toPerformedAt('2026-11-03', '23:30')).toBe('2026-11-03T22:30:00.000Z');
        expect(toPerformedAt('2026-07-03', '23:30')).toBe('2026-07-03T21:30:00.000Z');
    });

    it('crosses the UTC day for a time just after local midnight', () => {
        expect(toPerformedAt('2026-11-04', '00:30')).toBe('2026-11-03T23:30:00.000Z');
    });

    it('shows the local day and time of a stored UTC instant', () => {
        const state = stateFromRecord({ ...record, performed_at: '2026-11-03T23:30:00.000Z' });

        expect(state.date).toBe('2026-11-04');
        expect(state.time).toBe('00:30');
    });

    it('round-trips a record through the form unchanged', () => {
        const iso = '2026-11-03T22:30:00.000Z';
        const state = stateFromRecord({ ...record, performed_at: iso });

        expect(toPerformedAt(state.date, state.time)).toBe(iso);
    });
});

describe('addable items', () => {
    const live = [
        { id: 'x', name: 'Okná', price: '20.00', position: 0 },
        { id: 'y', name: 'Vstup', price: '10.00', position: 1 },
    ];

    it('drops items already in the record by their source id', () => {
        const withSource = { ...record, items: [{ ...record.items[0], object_visit_item_id: 'y' }] };

        expect(addableItems(live, withSource).map((i) => i.id)).toEqual(['x']);
    });

    it('keeps everything when the record has none or the source is gone', () => {
        expect(addableItems(live, null)).toHaveLength(2);
        expect(
            addableItems(live, { ...record, items: [{ ...record.items[0], object_visit_item_id: null }] }),
        ).toHaveLength(2);
    });
});

describe('items error', () => {
    it('reads the array keys and their indexed children', () => {
        expect(itemsError({ item_ids: 'a' })).toBe('a');
        expect(itemsError({ 'add_item_ids.1': 'b' })).toBe('b');
        expect(itemsError({ 'keep_item_ids.0': 'c' })).toBe('c');
        expect(itemsError({ note: 'x' })).toBeUndefined();
    });
});

describe('form state to payload', () => {
    it('builds the add payload with trimmed note', () => {
        const state = { ...newFormState(new Date(2026, 10, 13, 8, 0)), membershipId: 'm1', addItemIds: ['x', 'y'] };
        state.note = '  hotovo ';

        expect(toAddPayload(state)).toEqual({
            tenant_membership_id: 'm1',
            item_ids: ['x', 'y'],
            performed_at: new Date(2026, 10, 13, 8, 0).toISOString(),
            note: 'hotovo',
        });
    });

    it('builds the correction payload with kept and added ids', () => {
        const state = { ...stateFromRecord(record), keepItemIds: ['a'], addItemIds: ['z'], note: ' ' };

        expect(toUpdatePayload(state)).toEqual({
            tenant_membership_id: 'm1',
            performed_at: record.performed_at,
            note: null,
            keep_item_ids: ['a'],
            add_item_ids: ['z'],
        });
    });

    it('starts a correction with every stored item kept', () => {
        expect(stateFromRecord(record).keepItemIds).toEqual(['a', 'b']);
        expect(stateFromRecord({ ...record, tenant_membership_id: null }).membershipId).toBe('');
    });
});

describe('completeness', () => {
    it('counts only added items in add mode and both in correct mode', () => {
        const state = { ...stateFromRecord(record), keepItemIds: ['a'], addItemIds: ['z'] };

        expect(selectedItemCount(state, 'add')).toBe(1);
        expect(selectedItemCount(state, 'correct')).toBe(2);
    });

    it('requires an employee, a time and at least one item', () => {
        const base = { ...newFormState(), membershipId: 'm1', addItemIds: ['x'] };

        expect(isFormComplete(base, 'add')).toBe(true);
        expect(isFormComplete({ ...base, membershipId: '' }, 'add')).toBe(false);
        expect(isFormComplete({ ...base, time: null }, 'add')).toBe(false);
        expect(isFormComplete({ ...base, addItemIds: [] }, 'add')).toBe(false);
        expect(isFormComplete({ ...base, addItemIds: [], keepItemIds: [] }, 'correct')).toBe(false);
    });
});

describe('visit item rows', () => {
    it('creates rows with unique keys and the stored price as a number', () => {
        const a = newVisitItemRow({ id: 'i1', name: 'Vstup', price: '12.50', position: 0 });
        const b = newVisitItemRow();

        expect(a.price).toBe(12.5);
        expect(a.key).not.toBe(b.key);
        expect(b).toMatchObject({ id: null, name: '', price: null });
    });

    it('moves a row and ignores moves off the ends', () => {
        expect(moveRow(['a', 'b', 'c'], 1, -1)).toEqual(['b', 'a', 'c']);
        expect(moveRow(['a', 'b', 'c'], 1, 1)).toEqual(['a', 'c', 'b']);
        expect(moveRow(['a', 'b'], 0, -1)).toEqual(['a', 'b']);
    });

    it('drops the key and trims names in the payload', () => {
        const row = { ...newVisitItemRow(), name: ' Okná ', price: 0 };

        expect(toVisitItemsPayload([row, { ...newVisitItemRow(), name: 'x', price: null }])).toEqual([
            { id: null, name: 'Okná', price: 0 },
            { id: null, name: 'x', price: '' },
        ]);
    });
});
