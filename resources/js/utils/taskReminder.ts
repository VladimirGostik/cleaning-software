export interface ReminderState {
    remind_enabled: boolean;
    remind_on: string | null;
    remind_slot: App.Enums.TaskReminderSlotEnum;
}

export type ReminderPayload =
    | { remind_on: null; remind_slot: null }
    | { remind_at: string | null }
    | { remind_on: string | null; remind_slot: App.Enums.TaskReminderSlotEnum };

export function formatSlotTime(hour: number): string {
    return `${hour}:00`;
}

export function findReminderSlot(
    slots: App.Data.Tasks.TaskReminderSlotData[],
    slot: App.Enums.TaskReminderSlotEnum | null | undefined,
): App.Data.Tasks.TaskReminderSlotData | null {
    if (!slot) return null;

    return slots.find((s) => s.slot === slot) ?? null;
}

export function initialReminderState(
    task: App.Data.Tasks.TaskDetailData | null | undefined,
    context: App.Data.Tasks.TaskFormContextData,
): ReminderState {
    return {
        remind_enabled: !!task?.remind_on,
        remind_on: task?.remind_on ?? context.default_remind_on,
        remind_slot: task?.remind_slot ?? context.default_remind_slot,
    };
}

export function buildReminderPayload(
    state: ReminderState,
    task: App.Data.Tasks.TaskDetailData | null | undefined,
): ReminderPayload {
    if (!state.remind_enabled) {
        return { remind_on: null, remind_slot: null };
    }

    if (task && task.remind_on === state.remind_on && task.remind_slot === state.remind_slot) {
        return { remind_at: task.remind_at };
    }

    return { remind_on: state.remind_on, remind_slot: state.remind_slot };
}
