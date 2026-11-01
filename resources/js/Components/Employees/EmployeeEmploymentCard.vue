<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ContractEmploymentCard from '@/Components/Contracts/ContractEmploymentCard.vue';
import { employeeTypeKey } from '@/utils/enums';

defineProps<{
    employee: App.Data.Employees.EmployeeDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('employee_section_employment') }}</h2>

            <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <p class="text-xs text-base-content/50">{{ t('type') }}</p>
                    <p>
                        {{
                            employee.employee_type
                                ? t(employeeTypeKey(employee.employee_type))
                                : t('employee_type_unset')
                        }}
                    </p>
                </div>

                <template v-if="employee.employee_type === 'self_employed'">
                    <div>
                        <p class="text-xs text-base-content/50">{{ t('employee_company_ico') }}</p>
                        <p>{{ employee.company_ico ?? t('empty_dash') }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-base-content/50">{{ t('employee_company_name') }}</p>
                        <p>{{ employee.company_name ?? t('empty_dash') }}</p>
                    </div>
                </template>
            </div>

            <template v-if="employee.employment_contract">
                <ContractEmploymentCard :employment="employee.employment_contract" />
                <Link
                    :href="`/contracts/${employee.employment_contract_id}`"
                    class="link link-hover text-sm inline-block"
                >
                    {{ t('employee_view_contract') }}
                </Link>
            </template>
            <p v-else class="text-base-content/60">{{ t('employee_no_employment') }}</p>
        </div>
    </div>
</template>
