<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import EmployeeForm from '@/Components/Employees/EmployeeForm.vue';

import type { Breadcrumb } from '@/types';

defineProps<{
    context: App.Data.Employees.EmployeeFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('employees'), url: '/employees' },
    { label: t('employee_add') },
];
</script>

<template>
    <AppLayout>
        <Header :title="t('employee_add')" :breadcrumbs="breadcrumbs" />
        <EmployeeForm :context="context" />
    </AppLayout>
</template>
