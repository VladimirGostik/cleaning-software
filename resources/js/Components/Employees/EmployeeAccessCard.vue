<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { permissionLabelKey } from '@/utils/enums';
import EmployeeAccessBadge from './EmployeeAccessBadge.vue';

defineProps<{
    employee: App.Data.Employees.EmployeeDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="card-title">{{ t('employee_section_access') }}</h2>
                <EmployeeAccessBadge
                    :has-access="employee.has_access"
                    :invitation-pending="employee.invitation_pending"
                />
            </div>

            <template v-if="employee.has_access">
                <div>
                    <span v-if="employee.role_name" class="kg-chip kg-chip--neutral kg-chip--sm">
                        {{ employee.role_name }}
                    </span>
                    <span v-else class="text-muted">{{ t('employee_no_role') }}</span>
                </div>

                <ul v-if="employee.permissions.length > 0" class="flex flex-wrap gap-1.5">
                    <li v-for="p in employee.permissions" :key="p" class="kg-chip kg-chip--neutral kg-chip--sm">
                        {{ t(permissionLabelKey(p as App.Enums.PermissionEnum)) }}
                    </li>
                </ul>
                <p v-else class="text-muted">{{ t('employee_no_direct_permissions') }}</p>
            </template>

            <p v-else class="text-sm text-muted">{{ t('employee_has_app_access_hint') }}</p>
        </div>
    </div>
</template>
