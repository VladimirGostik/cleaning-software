<script setup lang="ts">
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import TextareaInput from '@/Components/Forms/TextareaInput.vue';
    import FormField from '@/Components/Forms/FormField.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    interface JobFormData {
        cleaning_object_id: string;
        type: App.Enums.JobTypeEnum | '';
        scheduled_date: string;
        start_time: string | null;
        end_time: string | null;
        assigned_membership_id: string | null;
        contract_id: string | null;
        note: string | null;
    }

    const props = defineProps<{
        job?: App.Data.Schedule.JobDetailData | null;
        typeOptions: SelectOption[];
        objectOptions: App.Data.Objects.ObjectOptionData[];
        membershipOptions: App.Data.Contracts.MembershipOptionData[];
    }>();

    const { t } = useTranslate();

    const isEditing = computed(() => !!props.job);

    const today = new Date().toISOString().slice(0, 10);

    const form = useForm<JobFormData>(
        isEditing.value ? 'put' : 'post',
        isEditing.value ? `/jobs/${props.job!.id}` : '/jobs',
        {
            cleaning_object_id: props.job?.cleaning_object_id ?? '',
            type: (props.job?.type as App.Enums.JobTypeEnum | undefined) ?? '',
            scheduled_date: props.job?.scheduled_date ?? today,
            start_time: props.job?.start_time ?? null,
            end_time: props.job?.end_time ?? null,
            assigned_membership_id: props.job?.assigned_membership_id ?? null,
            contract_id: props.job?.contract_id ?? null,
            note: props.job?.note ?? null,
        },
    );

    const objectSelectOptions = computed<SelectOption[]>(() =>
        props.objectOptions.map((o) => ({ value: o.id, label: o.name })),
    );

    const membershipSelectOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('schedule.assign_panel.placeholder') },
        ...props.membershipOptions.map((m) => ({ value: m.id, label: m.user_name })),
    ]);

    const objectSelectValue = computed({
        get: () => form.cleaning_object_id,
        set: (v: string | number) => {
            form.cleaning_object_id = String(v);
        },
    });

    const typeSelectValue = computed({
        get: () => form.type as string,
        set: (v: string | number) => {
            form.type = v as App.Enums.JobTypeEnum | '';
        },
    });

    const membershipSelectValue = computed({
        get: () => form.assigned_membership_id ?? '',
        set: (v: string | number) => {
            form.assigned_membership_id = v === '' ? null : String(v);
        },
    });

    const startTimeValue = computed({
        get: () => form.start_time ?? '',
        set: (v: string) => {
            form.start_time = v || null;
        },
    });

    const endTimeValue = computed({
        get: () => form.end_time ?? '',
        set: (v: string) => {
            form.end_time = v || null;
        },
    });

    const noteValue = computed({
        get: () => form.note ?? '',
        set: (v: string) => {
            form.note = v || null;
        },
    });

    function submit(): void {
        form.submit();
    }
</script>

<template>
    <FormProvider :form="form">
        <form novalidate @submit.prevent="submit">
            <div class="card bg-base-100 shadow-sm mb-6">
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Object -->
                        <FormField
                            :label="t('schedule.form.object')"
                            :error="form.errors.cleaning_object_id"
                            required
                        >
                            <SelectInput
                                v-model="objectSelectValue"
                                :options="objectSelectOptions"
                                :placeholder="t('schedule.form.object')"
                                :error="form.errors.cleaning_object_id"
                            />
                        </FormField>

                        <!-- Type -->
                        <FormField :label="t('schedule.form.type')" :error="form.errors.type" required>
                            <SelectInput
                                v-model="typeSelectValue"
                                :options="typeOptions"
                                :placeholder="t('schedule.form.type')"
                                :error="form.errors.type"
                            />
                        </FormField>

                        <!-- Date -->
                        <FormField
                            :label="t('schedule.form.date')"
                            :error="form.errors.scheduled_date"
                            required
                        >
                            <input
                                v-model="form.scheduled_date"
                                type="date"
                                class="input input-bordered w-full"
                                :aria-label="t('schedule.form.date')"
                                required
                            />
                        </FormField>

                        <!-- Assignee -->
                        <FormField
                            :label="t('schedule.form.assignee')"
                            :error="form.errors.assigned_membership_id"
                        >
                            <SelectInput
                                v-model="membershipSelectValue"
                                :options="membershipSelectOptions"
                                :error="form.errors.assigned_membership_id"
                            />
                        </FormField>

                        <!-- Start time -->
                        <FormField :label="t('schedule.form.start_time')" :error="form.errors.start_time">
                            <input
                                v-model="startTimeValue"
                                type="time"
                                class="input input-bordered w-full"
                                :aria-label="t('schedule.form.start_time')"
                            />
                        </FormField>

                        <!-- End time -->
                        <FormField :label="t('schedule.form.end_time')" :error="form.errors.end_time">
                            <input
                                v-model="endTimeValue"
                                type="time"
                                class="input input-bordered w-full"
                                :aria-label="t('schedule.form.end_time')"
                            />
                        </FormField>

                        <!-- Note -->
                        <div class="md:col-span-2">
                            <TextareaInput
                                v-model="noteValue"
                                :label="t('schedule.form.note')"
                                :error="form.errors.note"
                                :rows="3"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <FormActions
                :cancel-href="isEditing ? `/jobs/${job!.id}` : '/jobs'"
                :cancel-label="t('cancel')"
                :submit-label="t('save')"
                :processing="form.processing"
            />
        </form>
    </FormProvider>
</template>
