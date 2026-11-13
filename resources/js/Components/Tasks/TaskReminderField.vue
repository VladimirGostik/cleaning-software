<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import CalendarGrid from '@/Components/Forms/CalendarGrid.vue';
import RadioGroup from '@/Components/Forms/RadioGroup.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import type { RadioOption } from '@/Components/Forms/RadioGroup.vue';
import { useSaveOnClose } from '@/Composables/useSaveOnClose';
import { formatDate } from '@/utils/date';
import { taskReminderSlotKey } from '@/utils/enums';
import {
    buildReminderPayload,
    findReminderSlot,
    formatSlotTime,
    initialReminderState,
    type ReminderPayload,
    type ReminderState,
} from '@/utils/taskReminder';

const props = defineProps<{
    task: App.Data.Tasks.TaskDetailData;
    context: App.Data.Tasks.TaskFormContextData;
    editable: boolean;
    disabled: boolean;
    error?: string | null;
}>();

const emit = defineEmits<{
    save: [patch: ReminderPayload];
}>();

const { t } = useI18n();

const open = ref(false);
const state = ref<ReminderState>(initialReminderState(props.task, props.context));

watch(
    () => [props.task.remind_on, props.task.remind_slot],
    () => {
        if (!open.value) state.value = initialReminderState(props.task, props.context);
    },
);

const label = computed(() => {
    if (!props.task.remind_on || !props.task.remind_slot) return t('empty_dash');

    const found = findReminderSlot(props.context.reminder_slots, props.task.remind_slot);
    const name = t(taskReminderSlotKey(props.task.remind_slot));
    const slot = found ? t('task_reminder_slot_option', { slot: name, time: formatSlotTime(found.hour) }) : name;

    return t('task_reminder_at_slot', { date: formatDate(props.task.remind_on), slot });
});

const hasReminder = computed(() => !!props.task.remind_on && !!props.task.remind_slot);

const slotOptions = computed<RadioOption[]>(() =>
    props.context.reminder_slots.map((s) => ({
        value: s.slot,
        label: t('task_reminder_slot_option', { slot: t(taskReminderSlotKey(s.slot)), time: formatSlotTime(s.hour) }),
    })),
);

function update(change: Partial<ReminderState>): void {
    state.value = { ...state.value, ...change };
}

watch(open, (isOpen) => {
    if (isOpen) state.value = initialReminderState(props.task, props.context);
});

useSaveOnClose(open, (discarded) => {
    const current = buildReminderPayload(initialReminderState(props.task, props.context));
    const draft = buildReminderPayload(state.value);
    const incomplete = state.value.remind_enabled && state.value.remind_on === null;
    const changed = draft.remind_on !== current.remind_on || draft.remind_slot !== current.remind_slot;

    if (!discarded && !incomplete && changed) emit('save', draft);
    if (discarded || incomplete) state.value = initialReminderState(props.task, props.context);
});
</script>

<template>
    <AnchoredPopover v-if="editable" v-model:open="open" class="w-86 max-w-[calc(100vw-1rem)] space-y-3 p-4">
        <template #trigger="{ toggle, open: isOpen }">
            <button
                type="button"
                class="btn btn-ghost btn-sm kg-value-trigger -ms-3 justify-start"
                :disabled="disabled"
                aria-haspopup="dialog"
                :aria-expanded="isOpen"
                :aria-label="`${t('task_remind_at')}: ${hasReminder ? label : t('task_no_reminder')}`"
                @click="toggle"
            >
                <span v-if="hasReminder" class="truncate">{{ label }}</span>
                <span v-else class="kg-placeholder">{{ t('task_no_reminder') }}</span>
            </button>
        </template>

        <div role="dialog" :aria-label="t('task_remind_at')" class="space-y-3">
            <ToggleInput
                :model-value="state.remind_enabled"
                :label="t('task_reminder_toggle')"
                @update:model-value="update({ remind_enabled: $event })"
            />
            <template v-if="state.remind_enabled">
                <CalendarGrid
                    :model-value="state.remind_on"
                    :label="t('task_remind_on')"
                    @update:model-value="update({ remind_on: $event })"
                />
                <p v-if="error" class="text-sm text-error">{{ error }}</p>
                <RadioGroup
                    :model-value="state.remind_slot"
                    :label="t('task_remind_slot')"
                    :options="slotOptions"
                    @update:model-value="update({ remind_slot: $event as App.Enums.TaskReminderSlotEnum })"
                />
            </template>
        </div>
    </AnchoredPopover>
    <span v-else :class="{ 'kg-placeholder': !hasReminder }">{{ label }}</span>
    <p v-if="editable && error && !open" class="text-sm text-error">{{ error }}</p>
</template>
