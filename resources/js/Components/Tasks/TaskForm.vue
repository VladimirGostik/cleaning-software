<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import DateTimeInput from '@/Components/Forms/DateTimeInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

import { usePageProps } from '@/Composables/usePageProps';
import { toDatetimeInputValue } from '@/utils/date';
import { TASK_PRIORITIES, taskPriorityKey } from '@/utils/enums';

interface TaskFormData {
    title: string;
    priority: App.Enums.TaskPriorityEnum;
    description: string;
    due_at: string;
    remind_at: string;
    tenant_id: string | null;
    assigned_membership_id: string | null;
    taskable_type: string | null;
    taskable_id: string | null;
    source: App.Enums.TaskSourceEnum;
}

const GLOBAL_CHOICE = '__global';

const props = defineProps<{
    context: App.Data.Tasks.TaskFormContextData;
    task?: App.Data.Tasks.TaskDetailData | null;
}>();

const emit = defineEmits<{
    saved: [];
    cancel: [];
}>();

const { t } = useI18n();
const pageProps = usePageProps();

const isEdit = computed(() => !!props.task);

function defaultTenantId(): string | null {
    const activeId = pageProps.value.tenant.active?.id ?? null;
    const companies = props.context.companies;

    if (activeId && companies.some((c) => c.tenant_id === activeId)) {
        return activeId;
    }

    return companies[0]?.tenant_id ?? null;
}

const form = useForm<TaskFormData>(
    isEdit.value ? 'put' : 'post',
    isEdit.value ? `/tasks/${props.task!.id}` : '/tasks',
    {
        title: props.task?.title ?? '',
        priority: props.task?.priority ?? 'normal',
        description: props.task?.description ?? '',
        due_at: toDatetimeInputValue(props.task?.due_at),
        remind_at: toDatetimeInputValue(props.task?.remind_at),
        tenant_id: props.task ? props.task.tenant_id : defaultTenantId(),
        assigned_membership_id: props.task?.assigned_membership_id ?? null,
        taskable_type: props.task?.subject?.type ?? null,
        taskable_id: props.task?.subject?.id ?? null,
        source: props.task?.source ?? 'manual',
    },
);

const priorityOptions = computed<SelectOption[]>(() =>
    TASK_PRIORITIES.map((v) => ({ value: v, label: t(taskPriorityKey(v)) })),
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

const showAssignee = computed(() => !isEdit.value && form.tenant_id !== null && members.value.length > 0);

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

function toIso(local: string, original: string | null | undefined): string | null {
    if (local === '') return null;
    if (original && toDatetimeInputValue(original) === local) return original;

    return new Date(local).toISOString();
}

function buildPayload(data: TaskFormData): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        ...data,
        description: data.description === '' ? null : data.description,
        due_at: toIso(data.due_at, props.task?.due_at),
        remind_at: toIso(data.remind_at, props.task?.remind_at),
    };

    if (isEdit.value) {
        delete payload.tenant_id;
        delete payload.assigned_membership_id;
    }

    return payload;
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

                <SelectInput field="priority" :label="t('task_priority')" :options="priorityOptions" required />

                <DateTimeInput v-model="form.due_at" :label="t('task_due_at')" :error="form.errors.due_at" />
                <DateTimeInput v-model="form.remind_at" :label="t('task_remind_at')" :error="form.errors.remind_at" />

                <SelectInput
                    v-if="!isEdit && context.companies.length > 0"
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

            <div class="sticky bottom-0 border-t border-base-300 bg-base-100 px-6 py-3">
                <FormActions :processing="form.processing" @cancel="emit('cancel')" />
            </div>
        </form>
    </FormProvider>
</template>
