import { format, isValid, parseISO } from 'date-fns';
import { currentMonth, joinLocalDatetime, localDatetimeToIso, toDateInputValue } from '@/utils/date';
import { formatMoney } from '@/utils/money';

export type WorkRecordMode = 'add' | 'correct';

export interface WorkRecordFormState {
    membershipId: string;
    date: string | null;
    time: string | null;
    note: string;
    addItemIds: string[];
    keepItemIds: string[];
}

export interface VisitItemRow {
    key: string;
    id: string | null;
    name: string;
    price: number | null;
}

const TIME_VALUE_FORMAT = 'HH:mm';

export const CURRENCY_SYMBOL = '€';

export function hasNextMonth(month: string, today: Date = new Date()): boolean {
    return month < currentMonth(today);
}

export function formatAmount(amount: string | number | null | undefined, locale: string): string {
    return formatMoney(amount, 'EUR', locale);
}

export function newFormState(now: Date = new Date()): WorkRecordFormState {
    return {
        membershipId: '',
        date: toDateInputValue(now),
        time: format(now, TIME_VALUE_FORMAT),
        note: '',
        addItemIds: [],
        keepItemIds: [],
    };
}

export function stateFromRecord(record: App.Data.Schedule.JobWorkRecordData): WorkRecordFormState {
    const performedAt = new Date(record.performed_at);

    return {
        membershipId: record.tenant_membership_id ?? '',
        date: toDateInputValue(performedAt),
        time: format(performedAt, TIME_VALUE_FORMAT),
        note: record.note ?? '',
        addItemIds: [],
        keepItemIds: record.items.map((item) => item.id),
    };
}

export function toPerformedAt(date: string | null, time: string | null): string | null {
    if (!date || !time) return null;

    const local = joinLocalDatetime(date, time);

    return isValid(parseISO(local)) ? localDatetimeToIso(local) : null;
}

export function latestTimeFor(date: string | null, now: Date = new Date()): string | undefined {
    return date === toDateInputValue(now) ? format(now, TIME_VALUE_FORMAT) : undefined;
}

export function selectedItemCount(state: WorkRecordFormState, mode: WorkRecordMode): number {
    return mode === 'add' ? state.addItemIds.length : state.keepItemIds.length + state.addItemIds.length;
}

export function isFormComplete(state: WorkRecordFormState, mode: WorkRecordMode): boolean {
    return (
        state.membershipId !== '' &&
        toPerformedAt(state.date, state.time) !== null &&
        selectedItemCount(state, mode) > 0
    );
}

export function toAddPayload(state: WorkRecordFormState): App.Data.Schedule.WorkRecordAdminStoreData {
    return {
        tenant_membership_id: state.membershipId,
        item_ids: state.addItemIds,
        performed_at: toPerformedAt(state.date, state.time) ?? '',
        note: state.note.trim() || null,
    };
}

export function toUpdatePayload(state: WorkRecordFormState): App.Data.Schedule.WorkRecordUpdateData {
    return {
        tenant_membership_id: state.membershipId,
        performed_at: toPerformedAt(state.date, state.time) ?? '',
        note: state.note.trim() || null,
        keep_item_ids: state.keepItemIds,
        add_item_ids: state.addItemIds,
    };
}

let rowSequence = 0;

export function newVisitItemRow(source?: App.Data.Objects.ObjectVisitItemData): VisitItemRow {
    rowSequence += 1;

    return {
        key: `row-${rowSequence}`,
        id: source?.id ?? null,
        name: source?.name ?? '',
        price: source ? Number(source.price) : null,
    };
}

export function moveRow<T>(rows: readonly T[], index: number, delta: -1 | 1): T[] {
    const target = index + delta;
    if (target < 0 || target >= rows.length) return [...rows];

    const next = [...rows];
    [next[index], next[target]] = [next[target], next[index]];

    return next;
}

export function toVisitItemsPayload(rows: readonly VisitItemRow[]): App.Data.Objects.ObjectVisitItemInputData[] {
    return rows.map((row) => ({ id: row.id, name: row.name.trim(), price: row.price ?? '' }));
}

export function addableItems(
    items: readonly App.Data.Objects.ObjectVisitItemData[],
    record: App.Data.Schedule.JobWorkRecordData | null | undefined,
): App.Data.Objects.ObjectVisitItemData[] {
    const present = new Set((record?.items ?? []).map((item) => item.object_visit_item_id));

    return items.filter((item) => !present.has(item.id));
}

const ITEM_ERROR_KEYS = ['item_ids', 'add_item_ids', 'keep_item_ids'];

export function itemsError(errors: Record<string, string | undefined>): string | undefined {
    for (const key of ITEM_ERROR_KEYS) {
        const message = errors[key] ?? Object.entries(errors).find(([name]) => name.startsWith(`${key}.`))?.[1];
        if (message) return message;
    }

    return undefined;
}
