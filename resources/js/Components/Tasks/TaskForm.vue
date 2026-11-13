<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import TimeInput from '@/Components/Forms/TimeInput.vue';
import RadioGroup from '@/Components/Forms/RadioGroup.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
import type { RadioOption } from '@/Components/Forms/RadioGroup.vue';

import { usePageProps } from '@/Composables/usePageProps';
import { joinLocalDatetime, localDatetimeToIso } from '@/utils/date';
import {
    TASK_ACTIVE_STATUSES,
    TASK_PRIORITIES,
    taskPriorityKey,
    taskReminderSlotKey,
    taskStatusKey,
} from '@/utils/enums';
import { buildReminderPayload, formatSlotTime, initialReminderState } from '@/utils/taskReminder';

interface TaskFormData {
    title: string;
    status: App.Enums.TaskStatusEnum;
    priority: App.Enums.TaskPriorityEnum;
    description: string;
    due_at: string;
    remind_enabled: boolean;
    remind_on: string | null;
    remind_slot: App.Enums.TaskReminderSlotEnum;
    tenant_id: string | null;
    assigned_membership_id: string | null;
    taskable_type: string | null;
    taskable_id: string | null;
    source: App.Enums.TaskSourceEnum;
}

const GLOBAL_CHOICE = '__global';

const props = defineProps<{
    context: App.Data.Tasks.TaskFormContextData;
    initialStatus?: App.Enums.TaskStatusEnum | null;
}>();

const emit = defineEmits<{
    saved: [];
    cancel: [];
}>();

const { t } = useI18n();
const pageProps = usePageProps();

function defaultTenantId(): string | null {
    const activeId = pageProps.value.tenant.active?.id ?? null;
    const companies = props.context.companies;

    if (activeId && companies.some((c) => c.tenant_id === activeId)) {
        return activeId;
    }

    return companies[0]?.tenant_id ?? null;
}

const form = useForm<TaskFormData>('post', '/tasks', {
    title: '',
    status: props.initialStatus ?? 'open',
    priority: 'normal',
    description: '',
    due_at: '',
    ...initialReminderState(null, props.context),
    tenant_id: defaultTenantId(),
    assigned_membership_id: null,
    taskable_type: null,
    taskable_id: null,
    source: 'manual',
});

const dueDate = computed<string | null>({
    get: () => form.due_at.slice(0, 10) || null,
    set: (value) => {
        form.due_at = joinLocalDatetime(value, form.due_at.slice(11, 16) || null);
    },
});
const dueTime = computed<string | null>({
    get: () => form.due_at.slice(11, 16) || null,
    set: (value) => {
        if (form.due_at) form.due_at = joinLocalDatetime(form.due_at.slice(0, 10), value);
    },
});

const statusOptions = computed<SelectOption[]>(() =>
    TASK_ACTIVE_STATUSES.map((v) => ({ value: v, label: t(taskStatusKey(v)) })),
);

const priorityOptions = computed<SelectOption[]>(() =>
    TASK_PRIORITIES.map((v) => ({ value: v, label: t(taskPriorityKey(v)) })),
);

const reminderSlotOptions = computed<RadioOption[]>(() =>
    props.context.reminder_slots.map((s) => ({
        value: s.slot,
        label: t('task_reminder_slot_option', { slot: t(taskReminderSlotKey(s.slot)), time: formatSlotTime(s.hour) }),
    })),
);

const companyOptions = computed<SelectOption[]>(() => [
    ...props.context.companies.map((c) => ({ value: c.tenant_id, label: c.name })),
    { value: GLOBAL_CHOICE, label: t('task_global') },
]);

const companyChoice = computed(() => form.tenant_id ?? GLOBAL_CHOICE);

function onCompanyChange(value: string): void {
    form.tenant_id = value === GLOBAL_CHOICE ? null : value;
    form.assigned_membership_id = null;
    if (form.tenant_id === null) {
        form.taskable_type = null;
        form.taskable_id = null;
    }
}

const members = computed(() => (form.tenant_id ? (props.context.members[form.tenant_id] ?? []) : []));

const showAssignee = computed(() => form.tenant_id !== null && members.value.length > 0);

const assigneeOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('task_unassigned') },
    ...members.value.map((m) => ({ value: m.id, label: m.name })),
]);

function onAssigneeChange(value: string): void {
    form.assigned_membership_id = value === '' ? null : value;
}

watch(
    () => form.tenant_id,
    () => form.clearErrors(),
);

function buildPayload(data: TaskFormData): Record<string, unknown> {
    const { remind_enabled, remind_on, remind_slot, ...rest } = data;

    return {
        ...rest,
        description: data.description === '' ? null : data.description,
        due_at: localDatetimeToIso(data.due_at),
        ...buildReminderPayload({ remind_enabled, remind_on, remind_slot }),
    };
}

function submit(): void {
    form.transform((data: TaskFormData) => buildPayload(data)).submit({
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="flex h-full flex-col" @submit.prevent="submit">
            <div class="flex-1 space-y-4 p-6">
                <TextInput field="title" :label="t('task_title')" required autofocus />

                <TextareaInput
                    v-model="form.description"
                    :label="t('description')"
                    :error="form.errors.description"
                    :rows="4"
                />

                <SelectInput field="status" :label="t('status')" :options="statusOptions" required />

                <SelectInput field="priority" :label="t('task_priority')" :options="priorityOptions" required />

                <div class="grid grid-cols-[minmax(0,1fr)_8rem] items-start gap-3">
                    <DateInput v-model="dueDate" :label="t('task_due_at')" :error="form.errors.due_at" />
                    <TimeInput v-model="dueTime" :label="t('date_picker_time')" :disabled="!dueDate" />
                </div>

                <ToggleInput v-model="form.remind_enabled" :label="t('task_reminder_toggle')" />

                <template v-if="form.remind_enabled">
                    <DateInput v-model="form.remind_on" :label="t('task_remind_on')" :error="form.errors.remind_on" />
                    <RadioGroup
                        v-model="form.remind_slot"
                        :label="t('task_remind_slot')"
                        :options="reminderSlotOptions"
                        :error="form.errors.remind_slot"
                    />
                </template>

                <SelectInput
                    v-if="context.companies.length > 0"
                    :model-value="companyChoice"
                    :label="t('task_company')"
                    :options="companyOptions"
                    :error="form.errors.tenant_id"
                    required
                    @update:model-value="onCompanyChange"
                />

                <SelectInput
                    v-if="showAssignee"
                    :model-value="form.assigned_membership_id ?? ''"
                    :label="t('task_assignee')"
                    :options="assigneeOptions"
                    :error="form.errors.assigned_membership_id"
                    @update:model-value="onAssigneeChange"
                />

                <p v-if="form.errors.taskable_id || form.errors.taskable_type" class="text-sm text-error">
                    {{ form.errors.taskable_id ?? form.errors.taskable_type }}
                </p>
            </div>

            <div class="sticky bottom-0 bg-base-100 px-6 py-3">
                <FormActions :processing="form.processing" @cancel="emit('cancel')" />
            </div>
        </form>
    </FormProvider>
</template>
