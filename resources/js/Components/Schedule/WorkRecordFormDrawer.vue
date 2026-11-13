<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import SideDrawer from '@/Components/SideDrawer.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import CheckboxGroup, { type CheckboxOption } from '@/Components/Forms/CheckboxGroup.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import TimeInput from '@/Components/Forms/TimeInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { toDateInputValue } from '@/utils/date';
import {
    addableItems,
    formatAmount,
    isFormComplete,
    itemsError as firstItemsError,
    latestTimeFor,
    newFormState,
    selectedItemCount,
    stateFromRecord,
    toAddPayload,
    toUpdatePayload,
    type WorkRecordFormState,
    type WorkRecordMode,
} from '@/utils/workRecords';

const props = defineProps<{
    open: boolean;
    objectId: string;
    record?: App.Data.Schedule.JobWorkRecordData | null;
    items: App.Data.Objects.ObjectVisitItemData[];
    members: App.Data.Contracts.MembershipOptionData[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t, locale } = useI18n();

const mode = computed<WorkRecordMode>(() => (props.record ? 'correct' : 'add'));

const form = useForm<WorkRecordFormState>(newFormState());

form.transform((data: WorkRecordFormState) => (mode.value === 'add' ? toAddPayload(data) : toUpdatePayload(data)));

const errors = computed(() => form.errors as Record<string, string | undefined>);

watch(
    () => props.open,
    (open) => {
        if (!open) return;

        form.defaults(props.record ? stateFromRecord(props.record) : newFormState()).resetAndClearErrors();
    },
    { immediate: true },
);

const memberOptions = computed<SelectOption[]>(() => {
    const options = props.members.filter((m) => m.is_active).map((m) => ({ value: m.id, label: m.label }));
    const current = props.record;

    if (current?.tenant_membership_id && !options.some((o) => o.value === current.tenant_membership_id)) {
        options.push({ value: current.tenant_membership_id, label: current.recorded_by_name ?? t('empty_dash') });
    }

    return options;
});

function priced(name: string, price: string | null): string {
    return price === null ? name : `${name} — ${formatAmount(price, locale.value)}`;
}

const keptOptions = computed<CheckboxOption[]>(() =>
    (props.record?.items ?? []).map((item) => ({ value: item.id, label: priced(item.name, item.unit_price) })),
);

const addOptions = computed<CheckboxOption[]>(() =>
    addableItems(props.items, props.record).map((item) => ({
        value: item.id,
        label: priced(item.name, item.price),
    })),
);

const fixedMemberName = computed(() => (props.members.length === 0 ? (props.record?.recorded_by_name ?? null) : null));
const memberMissing = computed(() => form.membershipId === '');

const today = computed(() => toDateInputValue(new Date()));
const latestTime = computed(() => latestTimeFor(form.date));
const complete = computed(() => isFormComplete(form, mode.value));
const noneSelected = computed(() => selectedItemCount(form, mode.value) === 0);
const itemsError = computed(() => firstItemsError(errors.value));

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => emit('close') };

    if (props.record) form.submit('put', `/jobs/${props.record.job_id}/work-record`, options);
    else form.submit('post', `/objects/${props.objectId}/work-records`, options);
}
</script>

<template>
    <SideDrawer
        :open="open"
        :title="mode === 'add' ? t('work_record_add_title') : t('work_record_edit_title')"
        @close="emit('close')"
    >
        <FormProvider :form="form">
            <form novalidate class="flex h-full flex-col" @submit.prevent="submit">
                <div class="flex-1 space-y-4 p-6">
                    <SelectInput
                        v-if="members.length > 0 || fixedMemberName === null"
                        v-model="form.membershipId"
                        :label="t('work_record_employee')"
                        :options="memberOptions"
                        :placeholder="t('work_record_employee_placeholder')"
                        :error="errors.tenant_membership_id"
                        required
                    />
                    <div v-else>
                        <p class="text-xs text-muted">{{ t('work_record_employee') }}</p>
                        <p class="text-sm">{{ fixedMemberName }}</p>
                        <p v-if="errors.tenant_membership_id" class="mt-1 text-sm text-error" role="alert">
                            {{ errors.tenant_membership_id }}
                        </p>
                    </div>
                    <p v-if="memberMissing && members.length === 0" class="text-xs text-muted">
                        {{ t('work_record_employee_required') }}
                    </p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <DateInput
                            v-model="form.date"
                            :label="t('date')"
                            :max="today"
                            :error="errors.performed_at"
                            required
                        />
                        <TimeInput v-model="form.time" :label="t('work_record_time')" :max="latestTime" required />
                    </div>

                    <div v-if="keptOptions.length > 0" class="space-y-2">
                        <CheckboxGroup
                            v-model="form.keepItemIds"
                            :label="t('work_record_kept_items')"
                            :options="keptOptions"
                            :capitalize-labels="false"
                        />
                    </div>

                    <div v-if="addOptions.length > 0" class="space-y-2">
                        <CheckboxGroup
                            v-model="form.addItemIds"
                            :label="mode === 'add' ? t('work_record_items') : t('work_record_add_items')"
                            :options="addOptions"
                            :capitalize-labels="false"
                        />
                    </div>

                    <p v-if="noneSelected" class="text-xs text-muted">{{ t('work_record_items_required') }}</p>
                    <p v-if="itemsError" class="text-sm text-error" role="alert">{{ itemsError }}</p>

                    <TextareaInput
                        v-model="form.note"
                        :label="t('note')"
                        :rows="3"
                        :maxlength="2000"
                        :error="errors.note"
                    />
                </div>

                <div class="sticky bottom-0 bg-base-100 px-6 py-3">
                    <FormActions :processing="form.processing" :disabled="!complete" @cancel="emit('close')" />
                </div>
            </form>
        </FormProvider>
    </SideDrawer>
</template>
