<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import StatusErrorAlert from '@/Components/StatusErrorAlert.vue';

import ContractSetupStep from './ContractSetupStep.vue';
import ContractDocumentStep from './ContractDocumentStep.vue';

import { toNumber } from '@/utils/money';
import { toDateInputValue } from '@/utils/date';
import { contractCancelHref } from '@/utils/contracts';
import { CONTRACT_CATEGORIES, CONTRACT_CATEGORY_CONTRACTABLE_TYPES } from '@/utils/enums';
import { useContractDocument, type ContractFormData } from '@/Composables/useContractDocument';

const props = defineProps<{
    context: App.Data.Contracts.ContractFormContextData;
    contract?: App.Data.Contracts.ContractDetailData | null;
    prefill?: App.Data.Contracts.ContractCreatePrefillData | null;
}>();

const { t } = useI18n();

const isEditing = computed(() => !!props.contract);

const lockedLabel = computed(() =>
    !props.contract && props.prefill?.contractable_id ? (props.prefill.contractable_label ?? '') : null,
);

function toNullableNumber(value: string | null): number | null {
    return value === null ? null : toNumber(value);
}

function prefilledCategory(prefill: App.Data.Contracts.ContractCreatePrefillData): App.Enums.ContractCategoryEnum {
    const type = prefill.contractable_type;
    const allowed = CONTRACT_CATEGORIES.filter(
        (category) => type === null || CONTRACT_CATEGORY_CONTRACTABLE_TYPES[category].includes(type),
    );

    return prefill.category !== null && allowed.includes(prefill.category) ? prefill.category : allowed[0];
}

function initialEditData(contract: App.Data.Contracts.ContractDetailData): ContractFormData {
    return {
        title: contract.title,
        number: contract.number,
        category: contract.category,
        term_type: contract.term_type,
        contractable_type: contract.contractable_type,
        contractable_id: contract.contractable_id,
        document_template_id: contract.document_template_id,
        body: contract.body ?? '',
        show_header: contract.show_header,
        show_footer: contract.show_footer,
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

function initialCreateData(prefill: App.Data.Contracts.ContractCreatePrefillData | null): ContractFormData {
    const category = prefill ? prefilledCategory(prefill) : 'service_agreement';
    const allowedTypes = CONTRACT_CATEGORY_CONTRACTABLE_TYPES[category];
    const contractableType =
        prefill?.contractable_type && allowedTypes.includes(prefill.contractable_type)
            ? prefill.contractable_type
            : allowedTypes[0];

    return {
        title: '',
        number: null,
        category,
        term_type: 'indefinite',
        contractable_type: contractableType,
        contractable_id: prefill?.contractable_id ?? '',
        document_template_id: prefill?.document_template_id ?? null,
        body: '',
        show_header: true,
        show_footer: true,
        valid_from: toDateInputValue(new Date()),
        end_date: null,
        notes: null,
        employment:
            category === 'employment'
                ? {
                      employment_type: prefill?.employment_type ?? 'dpp',
                      position: null,
                      hourly_rate: null,
                      monthly_salary: null,
                      weekly_hours: null,
                      probation_end_date: null,
                  }
                : null,
    };
}

const form = useForm<ContractFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/contracts/${props.contract!.id}` : '/contracts',
    props.contract ? initialEditData(props.contract) : initialCreateData(props.prefill ?? null),
);

form.transform((data: ContractFormData) => ({
    ...data,
    number: data.number || null,
    notes: data.notes || null,
    document_template_id: data.document_template_id || null,
    end_date: data.term_type === 'fixed' ? data.end_date : null,
    employment: data.category === 'employment' ? data.employment : null,
}));

const doc = useContractDocument({
    form,
    tokens: () => props.context.tokens[form.category],
    initialBody: props.contract ? (props.contract.body ?? '') : null,
});

const step = ref<1 | 2>(isEditing.value ? 2 : 1);
const regenerateConfirmOpen = ref(false);

const cancelHref = computed(() => contractCancelHref(props.contract, props.prefill));

const DOCUMENT_STEP_FIELDS = ['body', 'show_header', 'show_footer', 'status'];

async function regenerate(): Promise<void> {
    if (await doc.generate()) step.value = 2;
}

function onContinue(): void {
    regenerateConfirmOpen.value = false;

    if (form.body.trim() === '' || (doc.isStale.value && !doc.isEdited.value)) {
        void regenerate();
        return;
    }

    if (doc.isStale.value) {
        regenerateConfirmOpen.value = true;
        return;
    }

    step.value = 2;
}

function keepText(): void {
    regenerateConfirmOpen.value = false;
    step.value = 2;
}

function confirmRegenerate(): void {
    regenerateConfirmOpen.value = false;
    void regenerate();
}

function save(): void {
    form.submit({
        onError: (errors: Record<string, string>) => {
            if (Object.keys(errors).some((field) => !DOCUMENT_STEP_FIELDS.includes(field))) step.value = 1;
        },
    });
}

function onSubmit(): void {
    if (step.value === 1) {
        onContinue();
        return;
    }

    save();
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="space-y-4" @submit.prevent="onSubmit">
            <ul class="steps w-full" :aria-label="t('contract_steps_label')">
                <li class="step step-primary" :aria-current="step === 1 ? 'step' : undefined">
                    {{ t('contract_step_setup') }}
                </li>
                <li
                    class="step"
                    :class="{ 'step-primary': step === 2 }"
                    :aria-current="step === 2 ? 'step' : undefined"
                >
                    {{ t('contract_step_document') }}
                </li>
            </ul>

            <StatusErrorAlert />

            <ContractSetupStep
                v-show="step === 1"
                :context="context"
                :locked-label="lockedLabel"
                :generating="doc.generating.value"
                :generate-error="doc.generateError.value"
                :cancel-href="cancelHref"
            />

            <ContractDocumentStep
                v-if="step === 2"
                v-model:body="form.body"
                v-model:show-header="form.show_header"
                v-model:show-footer="form.show_footer"
                :tokens="context.tokens[form.category]"
                :missing="doc.missing.value"
                :letterhead="context.letterhead"
                :footer="context.footer"
                :body-error="form.errors.body ?? null"
                :pdf-payload="doc.pdfPayload"
                :processing="form.processing"
                :submit-label="isEditing ? t('save') : t('contract_add')"
                @back="step = 1"
            />
        </form>

        <ConfirmDeleteModal
            :is-open="regenerateConfirmOpen"
            :title="t('contract_regenerate_title')"
            :description="t('contract_regenerate_description')"
            :confirm-label="t('contract_regenerate_confirm')"
            confirm-variant="warning"
            @cancel="keepText"
            @confirm="confirmRegenerate"
        />
    </FormProvider>
</template>
