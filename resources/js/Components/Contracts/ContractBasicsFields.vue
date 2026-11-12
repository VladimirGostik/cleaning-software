<script lang="ts">
export interface ContractBasicsFormData {
    title: string;
    number: string | null;
    term_type: App.Enums.ContractTermTypeEnum;
    valid_from: string;
    end_date: string | null;
}
</script>

<script setup lang="ts">
import { computed, watch } from 'vue';
import type { InertiaForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import TextInput from '@/Components/Forms/TextInput.vue';
import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import { callValidate } from '@/Components/Forms/useFieldError';
import { useFormContext } from '@/Components/Forms/useFormContext';

import { CONTRACT_TERM_TYPES, contractTermTypeKey, enumOptions } from '@/utils/enums';
import { useDependentValidation } from '@/Composables/useDependentValidation';

const { t } = useI18n();

const form = useFormContext() as InertiaForm<ContractBasicsFormData>;

useDependentValidation(form, {
    // end_date => after_or_equal:valid_from
    valid_from: ['end_date'],
    // end_date => required_if:term_type,fixed
    term_type: ['end_date'],
});

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
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
            <TextInput field="title" :label="t('contract_title')" required />
        </div>

        <div>
            <TextInput field="number" :label="t('contract_number')" maxlength="50" />
            <p class="text-xs text-muted">{{ t('contract_number_hint') }}</p>
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
</template>
