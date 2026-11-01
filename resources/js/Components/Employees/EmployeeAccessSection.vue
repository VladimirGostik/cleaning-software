<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Link } from '@inertiajs/vue3';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import EmployeeAccessBadge from './EmployeeAccessBadge.vue';
import EmployeeRoleFields from './EmployeeRoleFields.vue';

defineProps<{
    employee: App.Data.Employees.EmployeeDetailData | null;
    canManage: boolean;
    showRoleFields: boolean;
    roles: readonly App.Data.RoleListItemData[];
    permissionGroups: readonly App.Data.PermissionGroupData[];
    permissions: string[];
    permissionsError: string | null;
}>();

const emit = defineEmits<{
    'update:permissions': [string[]];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="space-y-4">
        <template v-if="employee === null">
            <template v-if="canManage">
                <div>
                    <ToggleInput field="has_app_access" :label="t('employee_has_app_access')" />
                    <p class="text-xs text-base-content/60">{{ t('employee_has_app_access_hint') }}</p>
                </div>
            </template>
            <p v-else class="text-sm text-base-content/60">{{ t('employee_access_admin_only_hint') }}</p>
        </template>

        <template v-else>
            <div class="flex flex-wrap items-center gap-3">
                <EmployeeAccessBadge
                    :has-access="employee.has_access"
                    :invitation-pending="employee.invitation_pending"
                />
                <span class="text-sm text-base-content/60">{{ t('employee_access_manage_on_detail') }}</span>
                <Link :href="`/employees/${employee.id}`" class="link link-hover text-sm">
                    {{ t('employee_access_open_detail') }}
                </Link>
            </div>
        </template>

        <EmployeeRoleFields
            v-if="showRoleFields"
            :roles="roles"
            :permission-groups="permissionGroups"
            :permissions="permissions"
            :permissions-error="permissionsError"
            @update:permissions="emit('update:permissions', $event)"
        />
    </div>
</template>
