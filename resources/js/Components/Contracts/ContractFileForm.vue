<script lang="ts">
import type { ContractBasicsFormData } from './ContractBasicsFields.vue';

export interface ContractFileFormData extends ContractBasicsFormData {
    category: App.Enums.ContractCategoryEnum;
    contractable_type: App.Enums.ContractableTypeEnum;
    contractable_id: string;
    is_signed: boolean;
    signed_on: string | null;
    notes: string | null;
    file_uuid: string | null;
}
</script>

<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import CheckboxInput from '@/Components/Forms/CheckboxInput.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { callValidate } from '@/Components/Forms/useFieldError';
import UploadedFileField from '@/Components/Documents/UploadedFileField.vue';
import StatusErrorAlert from '@/Components/StatusErrorAlert.vue';

import ContractBasicsFields from './ContractBasicsFields.vue';
import ContractSubjectFields from './ContractSubjectFields.vue';

import { contractCancelHref, fileAccept, initialFileFormData } from '@/utils/contracts';
import { formatBytes } from '@/utils/bytes';
import { toDateInputValue } from '@/utils/date';
import { CONTRACT_FILE_CATEGORIES, CONTRACT_FILE_CONTRACTABLE_TYPES } from '@/utils/enums';
import { useDependentValidation } from '@/Composables/useDependentValidation';

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

const form = useForm<ContractFileFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/contracts/${props.contract!.id}/file-details` : '/contracts/from-file',
    initialFileFormData(props.contract ?? props.prefill ?? null),
);

form.transform((data: ContractFileFormData) => ({
    ...data,
    number: data.number || null,
    notes: data.notes || null,
    end_date: data.term_type === 'fixed' ? data.end_date || null : null,
    signed_on: data.is_signed ? data.signed_on || null : null,
}));

useDependentValidation(form, {
    // signed_on => prohibited_unless:is_signed
    is_signed: ['signed_on'],
});

watch(
    () => form.is_signed,
    (signed) => {
        if (!signed) {
            form.signed_on = null;
            form.clearErrors('signed_on');
        }
    },
);

watch(
    () => form.category,
    () => form.clearErrors('contractable_type', 'contractable_id'),
);

const today = toDateInputValue(new Date());
const constraints = computed(() => props.context.file_constraints);
const accept = computed(() => fileAccept(constraints.value));
const fileHint = computed(() =>
    t('contract_file_upload_hint', {
        types: constraints.value.allowed_extensions.map((extension) => extension.toUpperCase()).join(' / '),
        size: formatBytes(constraints.value.max_size_kb * 1024),
    }),
);

const cancelHref = computed(() => contractCancelHref(props.contract, props.prefill));
const canSubmit = computed(() => isEditing.value || form.file_uuid !== null);

function onContractableTypeChange(type: App.Enums.ContractableTypeEnum): void {
    form.contractable_type = type;
    form.contractable_id = '';
    form.clearErrors('contractable_id');
}

function updateSignedOn(value: string | null): void {
    form.signed_on = value;
    callValidate(form, 'signed_on');
}

function submit(): void {
    form.submit();
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="space-y-4" @submit.prevent="submit">
            <StatusErrorAlert />

            <div class="card bg-base-100">
                <div class="card-body space-y-4">
                    <h2 class="card-title">{{ t('contract_section_file') }}</h2>

                    <UploadedFileField
                        v-model="form.file_uuid"
                        :current-file="contract?.main_file ?? null"
                        :accept="accept"
                        :max-size-kb="constraints.max_size_kb"
                        :hint="fileHint"
                        :error="form.errors.file_uuid ?? null"
                    />
                </div>
            </div>

            <div class="card bg-base-100">
                <div class="card-body space-y-4">
                    <h2 class="card-title">{{ t('contract_section_basics') }}</h2>

                    <ContractBasicsFields />

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-end">
                        <div class="flex min-h-(--kg-field-height) items-center">
                            <CheckboxInput field="is_signed" :label="t('contract_is_signed')" />
                        </div>

                        <DateInput
                            v-if="form.is_signed"
                            :model-value="form.signed_on"
                            :label="t('contract_signed_on')"
                            :max="today"
                            :error="form.errors.signed_on"
                            @update:model-value="updateSignedOn"
                        />
                        <p v-if="form.is_signed" class="text-xs text-muted md:col-start-2">
                            {{ t('contract_signed_on_hint') }}
                        </p>
                    </div>
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
                        :categories="CONTRACT_FILE_CATEGORIES"
                        :contractable-types="CONTRACT_FILE_CONTRACTABLE_TYPES"
                        @update:contractable-type="onContractableTypeChange"
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

            <FormActions
                :cancel-href="cancelHref"
                :submit-label="isEditing ? t('save') : t('contract_add')"
                :processing="form.processing"
                :disabled="!canSubmit"
            />
        </form>
    </FormProvider>
</template>
