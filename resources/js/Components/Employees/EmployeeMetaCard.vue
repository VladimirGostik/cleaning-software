<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { formatDate } from '@/utils/date';

const props = defineProps<{
    employee: App.Data.Employees.EmployeeDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-3 text-sm">
            <div>
                <p class="text-xs text-base-content/50">{{ t('employee_joined_at') }}</p>
                <p>{{ formatDate(props.employee.joined_at) }}</p>
            </div>

            <span v-if="props.employee.is_owner" class="badge badge-info badge-sm">{{ t('employee_owner') }}</span>

            <p v-if="props.employee.other_tenants_count > 0" class="text-base-content/70">
                {{ t('employee_other_tenants', { count: props.employee.other_tenants_count }) }}
            </p>

            <div>
                <p class="text-xs text-base-content/50">{{ t('employee_upcoming_jobs') }}</p>
                <p class="font-mono">{{ props.employee.upcoming_jobs_count }}</p>
            </div>
        </div>
    </div>
</template>
