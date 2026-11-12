<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { useFormContext } from '@/Components/Forms/useFormContext';

import ContractBasicsFields from './ContractBasicsFields.vue';
import ContractSubjectFields from './ContractSubjectFields.vue';
import EmploymentContractFields, { type EmploymentFormData } from './EmploymentContractFields.vue';

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

const templateOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('contract_no_template') },
    ...props.context.templates
        .filter(
            (tpl) =>
                tpl.type === form.category &&
                (form.category !== 'employment' ||
                    tpl.employment_type === null ||
                    tpl.employment_type === form.employment?.employment_type),
        )
        .map((tpl) => ({ value: tpl.id, label: tpl.name })),
]);

const employmentBlocked = computed(() => form.category === 'employment' && props.context.memberships.length === 0);

function onContractableTypeChange(type: App.Enums.ContractableTypeEnum): void {
    form.contractable_type = type;
    form.contractable_id = '';
    form.clearErrors('contractable_id');
}
</script>

<template>
    <div class="space-y-4">
        <div class="card bg-base-100">
            <div class="card-body space-y-4">
                <h2 class="card-title">{{ t('contract_section_basics') }}</h2>

                <ContractBasicsFields />
            </div>
        </div>

        <div class="card bg-base-100">
            <div class="card-body space-y-4">
                <h2 class="card-title">{{ t('contract_section_subject') }}</h2>

                <ContractSubjectFields
                    :context="context"
                    :contractable-type="form.contractable_type"
                    :category="form.category"
                    :current-contractable-id="form.contractable_id"
                    :locked-label="lockedLabel"
                    @update:contractable-type="onContractableTypeChange"
                />

                <SelectInput field="document_template_id" :label="t('contract_template')" :options="templateOptions" />
            </div>
        </div>

        <div v-if="form.category === 'employment' && form.employment" class="card bg-base-100">
            <div class="card-body space-y-4">
                <h2 class="card-title">{{ t('contract_section_employment') }}</h2>

                <EmploymentContractFields
                    :employment="form.employment"
                    :errors="form.errors"
                    :disabled="form.processing"
                    @update:employment="form.employment = $event"
                />
            </div>
        </div>

        <div class="card bg-base-100">
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
