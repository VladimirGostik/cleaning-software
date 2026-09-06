<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import SelectInput from '@/Components/Forms/SelectInput.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import NumberInput from '@/Components/Forms/NumberInput.vue';
import DateInput from '@/Components/Forms/DateInput.vue';

import { EMPLOYMENT_CONTRACT_TYPES, employmentTypeKey, enumOptions } from '@/utils/enums';

export interface EmploymentFormData {
    employment_type: App.Enums.EmploymentContractTypeEnum;
    position: string | null;
    hourly_rate: number | null;
    monthly_salary: number | null;
    weekly_hours: number | null;
    probation_end_date: string | null;
}

const props = defineProps<{
    employment: EmploymentFormData;
    errors: Record<string, string | undefined>;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:employment': [value: EmploymentFormData];
}>();

const { t } = useI18n();

const employmentTypeOptions = enumOptions(EMPLOYMENT_CONTRACT_TYPES, employmentTypeKey, t);

function update<K extends keyof EmploymentFormData>(key: K, value: EmploymentFormData[K]): void {
    emit('update:employment', { ...props.employment, [key]: value });
}
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
            <SelectInput
                :model-value="employment.employment_type"
                :label="t('contract_employment_type')"
                :options="employmentTypeOptions"
                required
                :disabled="disabled"
                :error="errors['employment.employment_type']"
                @update:model-value="update('employment_type', $event as App.Enums.EmploymentContractTypeEnum)"
            />
        </div>

        <TextInput
            :model-value="employment.position ?? ''"
            :label="t('contract_employment_position')"
            maxlength="255"
            :disabled="disabled"
            :error="errors['employment.position']"
            @update:model-value="update('position', $event || null)"
        />

        <NumberInput
            :model-value="employment.hourly_rate"
            :label="t('contract_employment_hourly_rate')"
            :min="0"
            :step="0.01"
            :disabled="disabled"
            :error="errors['employment.hourly_rate']"
            @update:model-value="update('hourly_rate', $event)"
        />

        <NumberInput
            :model-value="employment.monthly_salary"
            :label="t('contract_employment_monthly_salary')"
            :min="0"
            :step="0.01"
            :disabled="disabled"
            :error="errors['employment.monthly_salary']"
            @update:model-value="update('monthly_salary', $event)"
        />

        <NumberInput
            :model-value="employment.weekly_hours"
            :label="t('contract_employment_weekly_hours')"
            :min="0"
            :max="168"
            :step="0.5"
            :disabled="disabled"
            :error="errors['employment.weekly_hours']"
            @update:model-value="update('weekly_hours', $event)"
        />

        <DateInput
            :model-value="employment.probation_end_date"
            :label="t('contract_employment_probation_end_date')"
            :disabled="disabled"
            :error="errors['employment.probation_end_date']"
            @update:model-value="update('probation_end_date', $event)"
        />
    </div>
</template>
