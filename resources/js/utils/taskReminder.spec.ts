import { describe, expect, it } from 'vitest';
import { buildReminderPayload, findReminderSlot, formatSlotTime, initialReminderState } from './taskReminder';
import { taskReminderSlotKey } from './enums';

const slots: App.Data.Tasks.TaskReminderSlotData[] = [
    { slot: 'morning', hour: 7 },
    { slot: 'noon', hour: 12 },
    { slot: 'evening', hour: 19 },
];

function context(): App.Data.Tasks.TaskFormContextData {
    return {
        reminder_slots: slots,
        default_remind_on: '2026-11-05',
        default_remind_slot: 'morning',
    } as App.Data.Tasks.TaskFormContextData;
}

function task(overrides: Partial<App.Data.Tasks.TaskDetailData> = {}): App.Data.Tasks.TaskDetailData {
    return {
        remind_on: '2026-11-10',
        remind_slot: 'noon',
        remind_at: '2026-11-10T11:00:00Z',
        ...overrides,
    } as App.Data.Tasks.TaskDetailData;
}

describe('initialReminderState', () => {
    it('is off with defaults on create', () => {
        expect(initialReminderState(null, context())).toEqual({
            remind_enabled: false,
            remind_on: '2026-11-05',
            remind_slot: 'morning',
        });
    });

    it('is on with task values when task has a reminder', () => {
        expect(initialReminderState(task(), context())).toEqual({
            remind_enabled: true,
            remind_on: '2026-11-10',
            remind_slot: 'noon',
        });
    });

    it('is off with defaults when task has no reminder', () => {
        expect(initialReminderState(task({ remind_on: null, remind_slot: null, remind_at: null }), context())).toEqual({
            remind_enabled: false,
            remind_on: '2026-11-05',
            remind_slot: 'morning',
        });
    });
});

describe('buildReminderPayload', () => {
    it('clears when toggle is off', () => {
        const payload = buildReminderPayload(
            { remind_enabled: false, remind_on: '2026-11-05', remind_slot: 'morning' },
            null,
        );
        expect(payload).toEqual({ remind_on: null, remind_slot: null });
        expect(payload).not.toHaveProperty('remind_at');
    });

    it('clears when toggle is off on edit', () => {
        expect(
            buildReminderPayload({ remind_enabled: false, remind_on: '2026-11-10', remind_slot: 'noon' }, task()),
        ).toEqual({
            remind_on: null,
            remind_slot: null,
        });
    });

    it('sends the original remind_at when unchanged', () => {
        const payload = buildReminderPayload(
            { remind_enabled: true, remind_on: '2026-11-10', remind_slot: 'noon' },
            task(),
        );
        expect(payload).toEqual({ remind_at: '2026-11-10T11:00:00Z' });
        expect(payload).not.toHaveProperty('remind_on');
        expect(payload).not.toHaveProperty('remind_slot');
    });

    it('sends date and slot when changed on edit', () => {
        const payload = buildReminderPayload(
            { remind_enabled: true, remind_on: '2026-11-11', remind_slot: 'noon' },
            task(),
        );
        expect(payload).toEqual({ remind_on: '2026-11-11', remind_slot: 'noon' });
        expect(payload).not.toHaveProperty('remind_at');
    });

    it('sends date and slot on create', () => {
        const payload = buildReminderPayload(
            { remind_enabled: true, remind_on: '2026-11-05', remind_slot: 'evening' },
            null,
        );
        expect(payload).toEqual({ remind_on: '2026-11-05', remind_slot: 'evening' });
        expect(payload).not.toHaveProperty('remind_at');
    });
});

describe('formatSlotTime', () => {
    it('formats hours as H:mm', () => {
        expect(formatSlotTime(7)).toBe('7:00');
        expect(formatSlotTime(19)).toBe('19:00');
    });
});

describe('findReminderSlot', () => {
    it('finds a slot', () => {
        expect(findReminderSlot(slots, 'noon')).toEqual({ slot: 'noon', hour: 12 });
    });

    it('returns null on miss', () => {
        expect(findReminderSlot(slots, null)).toBeNull();
        expect(findReminderSlot([], 'noon')).toBeNull();
    });
});

describe('taskReminderSlotKey', () => {
    it('builds the i18n key', () => {
        expect(taskReminderSlotKey('evening')).toBe('task_reminder_slot_evening');
    });
});
