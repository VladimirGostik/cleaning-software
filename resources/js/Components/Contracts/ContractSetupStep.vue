<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import TextInput from '@/Components/Forms/TextInput.vue';
import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { callValidate } from '@/Components/Forms/useFieldError';
import { useFormContext } from '@/Components/Forms/useFormContext';

import ContractSubjectFields from './ContractSubjectFields.vue';
import EmploymentContractFields, { type EmploymentFormData } from './EmploymentContractFields.vue';

import { CONTRACT_TERM_TYPES, contractTermTypeKey, enumOptions } from '@/utils/enums';
import { useDependentValidation } from '@/Composables/useDependentValidation';
import type { ContractFormInstance } from '@/Composables/useContractDocument';

const props = defineProps<{
    context: App.Data.Contracts.ContractFormContextData;
    lockedLabel: string | null;
    generating: boolean;
    generateError: string | null;
    cancelHref: string;
}>();

const { t } = useI18n();

const form = useFormContext() as ContractFormInstance;

function blankEmployment(): EmploymentFormData {
    return {
        employment_type: 'dpp',
        position: null,
        hourly_rate: null,
        monthly_salary: null,
        weekly_hours: null,
        probation_end_date: null,
    };
}

useDependentValidation(form, {
    // end_date => after_or_equal:valid_from
    valid_from: ['end_date'],
    // end_date => required_if:term_type,fixed
    term_type: ['end_date'],
    // employment => required_if:category,employment
    category: ['employment'],
});

watch(
    () => form.category,
    (category) => {
        form.employment = category === 'employment' ? (form.employment ?? blankEmployment()) : null;
        form.clearErrors('contractable_type', 'contractable_id', 'employment');
    },
);

watch(
    () => [form.category, form.employment?.employment_type] as const,
    () => {
        const tpl = props.context.templates.find((candidate) => candidate.id === form.document_template_id);
        if (!tpl) return;
        const fits =
            tpl.type === form.category &&
            (form.category !== 'employment' ||
                tpl.employment_type === null ||
                tpl.employment_type === form.employment?.employment_type);
        if (!fits) form.document_template_id = null;
    },
);

watch(
    () => form.term_type,
    (termType) => {
        if (termType === 'indefinite') {
            form.end_date = null;
            form.clearErrors('end_date');
        }
    },
);

const termTypeOptions = computed<RadioOption[]>(() => enumOptions(CONTRACT_TERM_TYPES, contractTermTypeKey, t));

const employmentBlocked = computed(() => form.category === 'employment' && props.context.memberships.length === 0);

function onContractableTypeChange(type: App.Enums.ContractableTypeEnum): void {
    form.contractable_type = type;
    form.contractable_id = '';
    form.clearErrors('contractable_id');
}

function updateRequiredDate(value: string | null): void {
    form.valid_from = value ?? '';
    callValidate(form, 'valid_from');
}

function updateEndDate(value: string | null): void {
    form.end_date = value;
    callValidate(form, 'end_date');
}
</script>

<template>
    <div class="space-y-6">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-4">
                <h2 class="card-title text-base">{{ t('contract_section_basics') }}</h2>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <TextInput field="title" :label="t('contract_title')" required />
                    </div>

                    <div>
                        <TextInput field="number" :label="t('contract_number')" maxlength="50" />
                        <p class="text-xs text-base-content/60">{{ t('contract_number_hint') }}</p>
                    </div>

                    <RadioGroup field="term_type" :label="t('contract_term_type')" :options="termTypeOptions" />

                    <DateInput
                        :model-value="form.valid_from"
                        :label="t('contract_valid_from')"
                        required
                        :error="form.errors.valid_from"
                        @update:model-value="updateRequiredDate"
                    />

                    <DateInput
                        v-if="form.term_type === 'fixed'"
                        :model-value="form.end_date"
                        :label="t('contract_end_date')"
                        required
                        :min="form.valid_from"
                        :error="form.errors.end_date"
                        @update:model-value="updateEndDate"
                    />
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-4">
                <h2 class="card-title text-base">{{ t('contract_section_subject') }}</h2>

                <ContractSubjectFields
                    :context="context"
                    :contractable-type="form.contractable_type"
                    :category="form.category"
                    :employment-type="form.employment?.employment_type ?? null"
                    :current-contractable-id="form.contractable_id"
                    :locked-label="lockedLabel"
                    @update:contractable-type="onContractableTypeChange"
                />
            </div>
        </div>

        <div v-if="form.category === 'employment' && form.employment" class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-4">
                <h2 class="card-title text-base">{{ t('contract_section_employment') }}</h2>

                <EmploymentContractFields
                    :employment="form.employment"
                    :errors="form.errors"
                    :disabled="form.processing"
                    @update:employment="form.employment = $event"
                />
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <TextareaInput
                    :model-value="form.notes ?? ''"
                    :label="t('contract_notes')"
                    :error="form.errors.notes"
                    :rows="3"
                    @update:model-value="form.notes = $event"
                />
            </div>
        </div>

        <div v-if="generateError" class="alert alert-error" role="alert">
            <span>{{ generateError }}</span>
        </div>

        <FormActions
            :cancel-href="cancelHref"
            :submit-label="t('contract_step_continue')"
            :processing="generating"
            :disabled="employmentBlocked"
        />
    </div>
</template>
