<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { employmentTypeKey } from '@/utils/enums';
import { formatDecimal } from '@/utils/money';
import { formatDate } from '@/utils/date';

const props = defineProps<{
    employment: App.Data.Contracts.EmploymentContractData;
}>();

const { t, locale } = useI18n();

function decimal(value: string | null): string {
    if (value === null) return '';
    return formatDecimal(value, locale.value);
}
</script>

<template>
    <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
        <div>
            <p class="text-xs text-base-content/50">{{ t('contract_employment_type') }}</p>
            <p>{{ t(employmentTypeKey(props.employment.employment_type)) }}</p>
        </div>

        <div v-if="props.employment.position">
            <p class="text-xs text-base-content/50">{{ t('contract_employment_position') }}</p>
            <p>{{ props.employment.position }}</p>
        </div>

        <div v-if="props.employment.hourly_rate !== null">
            <p class="text-xs text-base-content/50">{{ t('contract_employment_hourly_rate') }}</p>
            <p>{{ decimal(props.employment.hourly_rate) }}</p>
        </div>

        <div v-if="props.employment.monthly_salary !== null">
            <p class="text-xs text-base-content/50">{{ t('contract_employment_monthly_salary') }}</p>
            <p>{{ decimal(props.employment.monthly_salary) }}</p>
        </div>

        <div v-if="props.employment.weekly_hours !== null">
            <p class="text-xs text-base-content/50">{{ t('contract_employment_weekly_hours') }}</p>
            <p>{{ decimal(props.employment.weekly_hours) }}</p>
        </div>

        <div v-if="props.employment.probation_end_date">
            <p class="text-xs text-base-content/50">{{ t('contract_employment_probation_end_date') }}</p>
            <p>{{ formatDate(props.employment.probation_end_date) }}</p>
        </div>
    </div>
</template>
