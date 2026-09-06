<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import RadioGroup from '@/Components/Forms/RadioGroup.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import TimeInput from '@/Components/Forms/TimeInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { callValidate } from '@/Components/Forms/useFieldError';

import { JOB_TYPES, jobTypeKey, enumOptions } from '@/utils/enums';
import { toDateInputValue } from '@/utils/date';

interface JobFormData {
    cleaning_object_id: string;
    type: App.Enums.JobTypeEnum;
    scheduled_date: string;
    start_time: string | null;
    end_time: string | null;
    assigned_membership_id: string | null;
    note: string | null;
}

const props = defineProps<{
    context: App.Data.Schedule.JobFormContextData;
    job?: App.Data.Schedule.JobDetailData | null;
}>();

const { t } = useI18n();

const isEditing = computed(() => !!props.job);

function initialData(): JobFormData {
    if (props.job) {
        const job = props.job;
        return {
            cleaning_object_id: job.cleaning_object_id,
            type: job.type,
            scheduled_date: toDateInputValue(job.scheduled_date),
            start_time: job.start_time?.slice(0, 5) ?? null,
            end_time: job.end_time?.slice(0, 5) ?? null,
            assigned_membership_id: null,
            note: job.note,
        };
    }

    return {
        cleaning_object_id: '',
        type: 'one_off',
        scheduled_date: toDateInputValue(new Date()),
        start_time: null,
        end_time: null,
        assigned_membership_id: null,
        note: null,
    };
}

const form = useForm<JobFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/jobs/${props.job!.id}` : '/jobs',
    initialData(),
);

form.transform((data: JobFormData) => {
    const { assigned_membership_id, ...rest } = data;

    return {
        ...rest,
        start_time: rest.start_time || null,
        end_time: rest.end_time || null,
        note: rest.note || null,
        ...(isEditing.value ? {} : { assigned_membership_id: assigned_membership_id || null }),
    };
});

const objectOptions = computed<SelectOption[]>(() =>
    props.context.objects.map((o) => ({ value: o.id, label: o.client_name ? `${o.name} — ${o.client_name}` : o.name })),
);

const assigneeOptions = computed<SelectOption[]>(() =>
    props.context.memberships.filter((m) => m.is_active).map((m) => ({ value: m.id, label: m.label })),
);

const showAssignee = computed(() => !isEditing.value && assigneeOptions.value.length > 0);
const typeOptions = computed(() => enumOptions(JOB_TYPES, jobTypeKey, t));

function updateRequiredDate(value: string | null): void {
    form.scheduled_date = value ?? '';
    callValidate(form, 'scheduled_date');
}

function updateTime(field: 'start_time' | 'end_time', value: string | null): void {
    form[field] = value;
    callValidate(form, field);
}

function submit(): void {
    form.submit();
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="space-y-6" @submit.prevent="submit">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body space-y-4">
                    <h2 class="card-title text-base">{{ t('schedule_section_details') }}</h2>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <SelectInput
                                field="cleaning_object_id"
                                :label="t('object')"
                                :options="objectOptions"
                                :placeholder="t('schedule_select_object')"
                                required
                            />
                        </div>

                        <div class="md:col-span-2">
                            <RadioGroup field="type" :label="t('schedule_form_type')" :options="typeOptions" required />
                        </div>

                        <DateInput
                            :model-value="form.scheduled_date"
                            :label="t('schedule_form_date')"
                            required
                            :error="form.errors.scheduled_date"
                            @update:model-value="updateRequiredDate"
                        />

                        <SelectInput
                            v-if="showAssignee"
                            field="assigned_membership_id"
                            :label="t('schedule_form_assignee')"
                            :options="[{ value: '', label: t('schedule_assignee_none') }, ...assigneeOptions]"
                        />

                        <TimeInput
                            :model-value="form.start_time"
                            :label="t('schedule_form_start_time')"
                            :error="form.errors.start_time"
                            @update:model-value="updateTime('start_time', $event)"
                        />

                        <TimeInput
                            :model-value="form.end_time"
                            :label="t('schedule_form_end_time')"
                            :min="form.start_time ?? undefined"
                            :error="form.errors.end_time"
                            @update:model-value="updateTime('end_time', $event)"
                        />

                        <div class="md:col-span-2">
                            <TextareaInput
                                :model-value="form.note ?? ''"
                                :label="t('note')"
                                :rows="3"
                                :error="form.errors.note"
                                @update:model-value="form.note = $event || null"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <FormActions
                :cancel-href="isEditing ? `/jobs/${props.job!.id}` : '/jobs'"
                :submit-label="isEditing ? t('save') : t('schedule_add')"
                :processing="form.processing"
            />
        </form>
    </FormProvider>
</template>
