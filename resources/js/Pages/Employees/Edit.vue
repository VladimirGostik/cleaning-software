<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import EmployeeForm from '@/Components/Employees/EmployeeForm.vue';

import type { Breadcrumb } from '@/types';

const props = defineProps<{
    employee: App.Data.Employees.EmployeeDetailData;
    context: App.Data.Employees.EmployeeFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('employees'), url: '/employees' },
    { label: props.employee.display_name, url: `/employees/${props.employee.id}` },
    { label: t('employee_edit') },
]);
</script>

<template>
    <AppLayout>
        <Header :title="t('employee_edit')" :breadcrumbs="breadcrumbs" />
        <EmployeeForm :context="context" :employee="employee" />
    </AppLayout>
</template>
