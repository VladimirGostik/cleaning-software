export interface ReminderState {
    remind_enabled: boolean;
    remind_on: string | null;
    remind_slot: App.Enums.TaskReminderSlotEnum;
}

export interface ReminderPayload {
    remind_on: string | null;
    remind_slot: App.Enums.TaskReminderSlotEnum | null;
}

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

export function buildReminderPayload(state: ReminderState): ReminderPayload {
    if (!state.remind_enabled) {
        return { remind_on: null, remind_slot: null };
    }

    return { remind_on: state.remind_on, remind_slot: state.remind_slot };
}
