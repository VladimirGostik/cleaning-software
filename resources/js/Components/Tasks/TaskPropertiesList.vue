<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CalendarIcon from '~icons/material-symbols/calendar-today-outline-rounded';

import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import DateTimePickerPanel from '@/Components/Forms/DateTimePickerPanel.vue';
import TaskAssigneeField from '@/Components/Tasks/TaskAssigneeField.vue';
import TaskPriorityMenu from '@/Components/Tasks/TaskPriorityMenu.vue';
import TaskPropertyRow from '@/Components/Tasks/TaskPropertyRow.vue';
import TaskReminderField from '@/Components/Tasks/TaskReminderField.vue';
import TaskStatusMenu from '@/Components/Tasks/TaskStatusMenu.vue';
import TaskSubjectLink from '@/Components/Tasks/TaskSubjectLink.vue';
import { useSaveOnClose } from '@/Composables/useSaveOnClose';
import { useTaskStatusChange } from '@/Composables/useTaskStatusChange';
import type { TaskPatch } from '@/Composables/useTaskInlineEdit';
import { formatDatetime, localDatetimeToIso, toDatetimeInputValue } from '@/utils/date';

const props = defineProps<{
    task: App.Data.Tasks.TaskDetailData;
    context: App.Data.Tasks.TaskFormContextData;
    editable: boolean;
    disabled: boolean;
    errors: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{
    save: [patch: TaskPatch];
}>();

const { t } = useI18n();
const { pending, change } = useTaskStatusChange();

const shownStatus = computed(() => pending.value[props.task.id] ?? props.task.status);
const members = computed(() => (props.task.tenant_id ? (props.context.members[props.task.tenant_id] ?? []) : []));
const canAssign = computed(() => props.task.can.assign && !props.task.is_global && members.value.length > 0);
const showDue = computed(() => props.editable || props.task.due_at !== null);
const showReminder = computed(() => props.editable || (!!props.task.remind_on && !!props.task.remind_slot));
const showAssignee = computed(() => canAssign.value || props.task.assigned_membership_id !== null);

const dueOpen = ref(false);
const dueDraft = ref('');
const dueBaseline = ref('');

watch(dueOpen, (isOpen) => {
    if (isOpen) dueBaseline.value = dueDraft.value = toDatetimeInputValue(props.task.due_at);
});

useSaveOnClose(dueOpen, (discarded) => {
    if (discarded || dueDraft.value === dueBaseline.value) return;
    emit('save', { due_at: localDatetimeToIso(dueDraft.value, props.task.due_at) });
});

function commitDue(value: string): void {
    dueDraft.value = dueBaseline.value = value;
    dueOpen.value = false;
    emit('save', { due_at: localDatetimeToIso(value, props.task.due_at) });
}

function clearDue(): void {
    dueDraft.value = dueBaseline.value = '';
    dueOpen.value = false;
    emit('save', { due_at: null });
}
</script>

<template>
    <dl class="text-sm">
        <TaskPropertyRow :label="t('status')">
            <TaskStatusMenu
                variant="chip"
                :status="shownStatus"
                :editable="task.can.update"
                :disabled="pending[task.id] !== undefined || disabled"
                @select="change(task, $event)"
            />
        </TaskPropertyRow>

        <TaskPropertyRow :label="t('task_priority')">
            <TaskPriorityMenu
                :priority="task.priority"
                :editable="editable"
                :disabled="disabled"
                @select="emit('save', { priority: $event })"
            />
        </TaskPropertyRow>

        <TaskPropertyRow v-if="showDue" :label="t('task_due_at')">
            <AnchoredPopover v-if="editable" v-model:open="dueOpen" class="w-86 max-w-[calc(100vw-1rem)] p-4">
                <template #trigger="{ toggle, open: isOpen }">
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm kg-value-trigger -ms-3 justify-start"
                        :disabled="disabled"
                        aria-haspopup="dialog"
                        :aria-expanded="isOpen"
                        :aria-label="`${t('task_due_at')}: ${task.due_at ? formatDatetime(task.due_at) : t('task_no_due_date')}`"
                        @click="toggle"
                    >
                        <CalendarIcon aria-hidden="true" />
                        <span v-if="task.due_at">{{ formatDatetime(task.due_at) }}</span>
                        <span v-else class="kg-placeholder">{{ t('task_no_due_date') }}</span>
                    </button>
                </template>

                <div role="dialog" :aria-label="t('task_due_at')">
                    <DateTimePickerPanel
                        v-model="dueDraft"
                        :label="t('task_due_at')"
                        @commit="commitDue"
                        @clear="clearDue"
                    />
                    <p v-if="errors.due_at" class="mt-2 text-sm text-error">{{ errors.due_at }}</p>
                </div>
            </AnchoredPopover>
            <template v-else>
                <span v-if="task.due_at">{{ formatDatetime(task.due_at) }}</span>
                <span v-else class="kg-placeholder">{{ t('task_no_due_date') }}</span>
            </template>
            <p v-if="editable && errors.due_at && !dueOpen" class="text-sm text-error">{{ errors.due_at }}</p>
        </TaskPropertyRow>

        <TaskPropertyRow v-if="showReminder" :label="t('task_remind_at')">
            <TaskReminderField
                :task="task"
                :context="context"
                :editable="editable"
                :disabled="disabled"
                :error="errors.remind_on ?? errors.remind_slot"
                @save="emit('save', $event)"
            />
        </TaskPropertyRow>

        <TaskPropertyRow v-if="showAssignee" :label="t('task_assignee')">
            <TaskAssigneeField :task="task" :members="members" :editable="canAssign" :disabled="disabled" />
        </TaskPropertyRow>

        <TaskPropertyRow v-if="task.subject" :label="t('task_subject')">
            <TaskSubjectLink :subject="task.subject" />
        </TaskPropertyRow>
    </dl>
</template>
