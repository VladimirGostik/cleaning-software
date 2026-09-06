<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import EmploymentContractFields, { type EmploymentFormData } from '@/Components/Contracts/EmploymentContractFields.vue';

const props = defineProps<{
    employment: EmploymentFormData | null;
    errors: Record<string, string | undefined>;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:employment': [EmploymentFormData | null];
}>();

const { t } = useI18n();

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

function onToggle(value: boolean): void {
    emit('update:employment', value ? blankEmployment() : null);
}
</script>

<template>
    <div class="space-y-4">
        <ToggleInput
            :model-value="props.employment !== null"
            :label="t('employee_add_employment')"
            @update:model-value="onToggle"
        />

        <EmploymentContractFields
            v-if="props.employment"
            :employment="props.employment"
            :errors="props.errors"
            :disabled="props.disabled"
            @update:employment="emit('update:employment', $event)"
        />
    </div>
</template>
