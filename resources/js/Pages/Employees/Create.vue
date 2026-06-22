<script setup lang="ts">
    import { Link } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmployeeForm from '@/Components/Employees/EmployeeForm.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
    import type { PermissionGroup } from '@/Components/Employees/PermissionCheckboxGroups.vue';

    interface Props {
        roleOptions: SelectOption[];
        permissionGroups: PermissionGroup[];
        employmentTypeOptions: SelectOption[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
</script>

<template>
    <div class="page-container">
        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/employees">{{ t('employees.title') }}</Link>
                </li>
                <li>{{ t('employees.create_title') }}</li>
            </ul>
        </div>

        <PageHeader :title="t('employees.create_title')" />

        <EmployeeForm
            mode="create"
            :role-options="props.roleOptions"
            :permission-groups="props.permissionGroups"
            :employment-type-options="props.employmentTypeOptions"
        />
    </div>
</template>
