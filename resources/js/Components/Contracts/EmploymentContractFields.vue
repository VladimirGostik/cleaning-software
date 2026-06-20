<script setup lang="ts">
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import NumberInput from '@/Components/Forms/NumberInput.vue';
    import FormField from '@/Components/Forms/FormField.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    export interface ContractFormEmploymentData {
        employment_type: App.Enums.EmploymentContractTypeEnum;
        position: string | null;
        hourly_rate: number | null;
        monthly_salary: number | null;
        weekly_hours: number | null;
        probation_end_date: string | null;
    }

    const props = withDefaults(
        defineProps<{
            employment: ContractFormEmploymentData;
            errors: Record<string, string>;
            employmentTypeOptions: SelectOption[];
            disabled?: boolean;
        }>(),
        {
            disabled: false,
        },
    );

    const emit = defineEmits<{
        'update:employment': [ContractFormEmploymentData];
    }>();

    const { t } = useTranslate();

    function update<K extends keyof ContractFormEmploymentData>(
        key: K,
        value: ContractFormEmploymentData[K],
    ): void {
        emit('update:employment', { ...props.employment, [key]: value });
    }
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <SelectInput
                :model-value="employment.employment_type"
                :options="employmentTypeOptions"
                :label="t('contracts.form.employment_type')"
                :error="errors['employment.employment_type']"
                :disabled="disabled"
                required
                @update:model-value="
                    update('employment_type', $event as App.Enums.EmploymentContractTypeEnum)
                "
            />
        </div>

        <TextInput
            :model-value="employment.position ?? ''"
            :label="t('contracts.form.employment_position')"
            :error="errors['employment.position']"
            :disabled="disabled"
            @update:model-value="update('position', $event || null)"
        />

        <NumberInput
            :model-value="employment.hourly_rate"
            :label="t('contracts.form.employment_hourly_rate')"
            :error="errors['employment.hourly_rate']"
            :disabled="disabled"
            :min="0"
            :step="0.01"
            @update:model-value="update('hourly_rate', $event)"
        />

        <NumberInput
            :model-value="employment.monthly_salary"
            :label="t('contracts.form.employment_monthly_salary')"
            :error="errors['employment.monthly_salary']"
            :disabled="disabled"
            :min="0"
            :step="0.01"
            @update:model-value="update('monthly_salary', $event)"
        />

        <NumberInput
            :model-value="employment.weekly_hours"
            :label="t('contracts.form.employment_weekly_hours')"
            :error="errors['employment.weekly_hours']"
            :disabled="disabled"
            :min="0"
            :step="0.5"
            @update:model-value="update('weekly_hours', $event)"
        />

        <FormField
            :label="t('contracts.form.employment_probation_end_date')"
            :error="errors['employment.probation_end_date']"
        >
            <input
                :value="employment.probation_end_date ?? ''"
                type="date"
                :disabled="disabled"
                class="input input-bordered w-full"
                :class="{ 'input-error': errors['employment.probation_end_date'] }"
                @input="update('probation_end_date', ($event.target as HTMLInputElement).value || null)"
            />
        </FormField>
    </div>
</template>
