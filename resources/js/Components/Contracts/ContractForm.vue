<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { callValidate } from '@/Components/Forms/useFieldError';

import ContractSubjectFields from './ContractSubjectFields.vue';
import ContractBodyEditor from './ContractBodyEditor.vue';
import EmploymentContractFields, { type EmploymentFormData } from './EmploymentContractFields.vue';

import { toNumber } from '@/utils/money';
import { toDateInputValue } from '@/utils/date';
import { CONTRACT_CATEGORY_CONTRACTABLE, CONTRACT_TERM_TYPES, contractTermTypeKey, enumOptions } from '@/utils/enums';

interface ContractFormData {
    title: string;
    number: string | null;
    category: App.Enums.ContractCategoryEnum;
    term_type: App.Enums.ContractTermTypeEnum;
    contractable_type: App.Enums.ContractableTypeEnum;
    contractable_id: string;
    contract_template_id: string | null;
    body: string;
    valid_from: string;
    end_date: string | null;
    notes: string | null;
    employment: EmploymentFormData | null;
}

const props = defineProps<{
    context: App.Data.Contracts.ContractFormContextData;
    contract?: App.Data.Contracts.ContractDetailData | null;
}>();

const { t } = useI18n();

const isEditing = computed(() => !!props.contract);

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

function toNullableNumber(value: string | null): number | null {
    return value === null ? null : toNumber(value);
}

function initialData(): ContractFormData {
    if (props.contract) {
        const contract = props.contract;
        return {
            title: contract.title,
            number: contract.number,
            category: contract.category,
            term_type: contract.term_type,
            contractable_type: contract.contractable_type,
            contractable_id: contract.contractable_id,
            contract_template_id: contract.contract_template_id,
            body: contract.body,
            valid_from: toDateInputValue(contract.valid_from),
            end_date: toDateInputValue(contract.end_date) || null,
            notes: contract.notes,
            employment: contract.employment
                ? {
                      employment_type: contract.employment.employment_type,
                      position: contract.employment.position,
                      hourly_rate: toNullableNumber(contract.employment.hourly_rate),
                      monthly_salary: toNullableNumber(contract.employment.monthly_salary),
                      weekly_hours: toNullableNumber(contract.employment.weekly_hours),
                      probation_end_date: contract.employment.probation_end_date,
                  }
                : null,
        };
    }

    return {
        title: '',
        number: null,
        category: 'service_agreement',
        term_type: 'indefinite',
        contractable_type: 'cleaning_object',
        contractable_id: '',
        contract_template_id: null,
        body: '',
        valid_from: toDateInputValue(new Date()),
        end_date: null,
        notes: null,
        employment: null,
    };
}

const form = useForm<ContractFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/contracts/${props.contract!.id}` : '/contracts',
    initialData(),
);

form.transform((data: ContractFormData) => ({
    ...data,
    number: data.number || null,
    notes: data.notes || null,
    contract_template_id: data.contract_template_id || null,
    end_date: data.term_type === 'fixed' ? data.end_date : null,
    employment: data.category === 'employment' ? data.employment : null,
}));

const ui = reactive({
    lastAppliedBody: '',
    templateBodyKept: false,
});

const templateOptionsForCategory = computed(() =>
    props.context.templates.filter((tpl) => tpl.category === form.category),
);

watch(
    () => form.category,
    (cat) => {
        const expected = CONTRACT_CATEGORY_CONTRACTABLE[cat];
        if (expected && expected !== form.contractable_type) {
            form.contractable_type = expected;
            form.contractable_id = '';
        }
        form.employment = cat === 'employment' ? (form.employment ?? blankEmployment()) : null;
        if (
            form.contract_template_id &&
            !templateOptionsForCategory.value.some((o) => o.id === form.contract_template_id)
        ) {
            form.contract_template_id = null;
        }
        form.clearErrors('contractable_type', 'contractable_id', 'employment');
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

watch(
    () => form.contract_template_id,
    (id) => {
        if (!id) {
            ui.templateBodyKept = false;
            return;
        }

        const template = props.context.templates.find((tpl) => tpl.id === id);
        if (!template) return;

        if (form.body.trim() === '' || form.body === ui.lastAppliedBody) {
            form.body = template.body;
            ui.lastAppliedBody = template.body;
            ui.templateBodyKept = false;
        } else {
            ui.templateBodyKept = true;
        }
    },
);

const activeTokens = computed(() => props.context.tokens[form.contractable_type]);
const termTypeOptions = computed<RadioOption[]>(() => enumOptions(CONTRACT_TERM_TYPES, contractTermTypeKey, t));

function onContractableTypeChange(type: App.Enums.ContractableTypeEnum): void {
    form.contractable_type = type;
    form.contractable_id = '';
    form.clearErrors('contractable_id');
}

function updateRequiredDate(field: 'valid_from', value: string | null): void {
    form[field] = value ?? '';
    callValidate(form, field);
}

function updateNullableDate(field: 'end_date', value: string | null): void {
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
                            @update:model-value="updateRequiredDate('valid_from', $event)"
                        />

                        <DateInput
                            v-if="form.term_type === 'fixed'"
                            :model-value="form.end_date"
                            :label="t('contract_end_date')"
                            required
                            :min="form.valid_from"
                            :error="form.errors.end_date"
                            @update:model-value="updateNullableDate('end_date', $event)"
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
                        :current-contractable-id="form.contractable_id"
                        :template-body-kept="ui.templateBodyKept"
                        @update:contractable-type="onContractableTypeChange"
                    />
                </div>
            </div>

            <div class="card bg-base-100 shadow-sm">
                <div class="card-body space-y-4">
                    <h2 class="card-title text-base">{{ t('contract_section_body') }}</h2>

                    <ContractBodyEditor
                        :model-value="form.body"
                        :error="form.errors.body"
                        :tokens="activeTokens"
                        :label="t('contract_body')"
                        :placeholder="t('contract_body_placeholder')"
                        :hint="t('contract_body_resolve_hint')"
                        required
                        @update:model-value="form.body = $event"
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

            <FormActions
                :cancel-href="isEditing ? `/contracts/${props.contract!.id}` : '/contracts'"
                :submit-label="isEditing ? t('save') : t('contract_add')"
                :processing="form.processing"
            />
        </form>
    </FormProvider>
</template>
