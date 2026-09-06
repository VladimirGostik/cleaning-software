<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ContractEmploymentCard from '@/Components/Contracts/ContractEmploymentCard.vue';
import { permissionLabelKey } from '@/utils/enums';

const props = defineProps<{
    employee: App.Data.Employees.EmployeeDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-6">
            <div>
                <h2 class="card-title text-base">{{ t('employee_section_role') }}</h2>

                <div class="mt-2">
                    <span v-if="props.employee.role_name" class="badge badge-primary badge-sm">
                        {{ props.employee.role_name }}
                    </span>
                    <span v-else class="text-base-content/60">{{ t('employee_no_role') }}</span>
                </div>

                <ul v-if="props.employee.permissions.length > 0" class="flex flex-wrap gap-1.5 mt-3">
                    <li v-for="p in props.employee.permissions" :key="p" class="badge badge-outline badge-sm">
                        {{ t(permissionLabelKey(p as App.Enums.PermissionEnum)) }}
                    </li>
                </ul>
                <p v-else class="text-base-content/60 mt-3">{{ t('employee_no_direct_permissions') }}</p>
            </div>

            <div>
                <h3 class="text-sm font-medium">{{ t('employee_section_employment') }}</h3>

                <template v-if="props.employee.employment_contract">
                    <ContractEmploymentCard :employment="props.employee.employment_contract" />
                    <a
                        :href="`/contracts/${props.employee.employment_contract_id}`"
                        class="link link-hover text-sm mt-2 inline-block"
                    >
                        {{ t('employee_view_contract') }}
                    </a>
                </template>
                <p v-else class="text-base-content/60">{{ t('employee_no_employment') }}</p>
            </div>
        </div>
    </div>
</template>
