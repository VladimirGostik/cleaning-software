<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import PermissionManager from '@/Components/PermissionManager.vue';

const props = defineProps<{
    roles: readonly App.Data.RoleListItemData[];
    permissionGroups: readonly App.Data.PermissionGroupData[];
    permissions: string[];
    permissionsError: string | null;
}>();

const emit = defineEmits<{
    'update:permissions': [string[]];
}>();

const { t } = useI18n();

const roleOptions = computed<SelectOption[]>(() => props.roles.map((r) => ({ value: r.name, label: r.name })));
</script>

<template>
    <div class="space-y-4">
        <div class="md:max-w-sm">
            <SelectInput
                field="role_name"
                required
                :label="t('employee_role')"
                :placeholder="t('select_role')"
                :options="roleOptions"
            />
            <p class="text-xs text-base-content/60">{{ t('employee_role_subset_hint') }}</p>
        </div>

        <div>
            <h3 class="text-sm font-medium">{{ t('employee_permissions') }}</h3>
            <p class="text-xs text-base-content/60 mb-2">{{ t('employee_permissions_hint') }}</p>
            <PermissionManager
                :groups="permissionGroups as App.Data.PermissionGroupData[]"
                :model-value="permissions"
                @update:model-value="emit('update:permissions', $event)"
            />
            <p v-if="permissionsError" class="text-error text-sm">{{ permissionsError }}</p>
        </div>
    </div>
</template>
