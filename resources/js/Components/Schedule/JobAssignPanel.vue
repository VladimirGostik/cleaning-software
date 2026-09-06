<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';

const props = defineProps<{
    jobId: string;
    currentMembershipId: string | null;
    membershipOptions: readonly App.Data.Contracts.MembershipOptionData[];
}>();

const { t } = useI18n();

interface AssignFormData {
    assigned_membership_id: string;
}

function makeForm(jobId: string, current: string | null) {
    return useForm('post', `/jobs/${jobId}/assign`, {
        assigned_membership_id: current ?? '',
    } as AssignFormData).transform((data: AssignFormData) => ({
        assigned_membership_id: data.assigned_membership_id || null,
    }));
}

const form = shallowRef(makeForm(props.jobId, props.currentMembershipId));

watch(
    () => props.jobId,
    (jobId) => {
        form.value = makeForm(jobId, props.currentMembershipId);
    },
);

const assigneeOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('schedule_assign_unassign') },
    ...props.membershipOptions.filter((m) => m.is_active).map((m) => ({ value: m.id, label: m.label })),
]);

const isUnchanged = computed(() => form.value.assigned_membership_id === (props.currentMembershipId ?? ''));

function submit(): void {
    form.value.submit({ preserveScroll: true });
}
</script>

<template>
    <FormProvider :form="form">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-3">
                <h2 class="card-title text-base">{{ t('schedule_assign_title') }}</h2>

                <form novalidate @submit.prevent="submit">
                    <SelectInput
                        field="assigned_membership_id"
                        :label="t('schedule_col_assignee')"
                        :options="assigneeOptions"
                    />

                    <button
                        type="submit"
                        class="btn btn-primary btn-sm w-full"
                        :disabled="isUnchanged || form.processing"
                    >
                        <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                        {{ t('schedule_assign_submit') }}
                    </button>
                </form>
            </div>
        </div>
    </FormProvider>
</template>
