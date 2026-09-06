<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import JobForm from '@/Components/Schedule/JobForm.vue';

import type { Breadcrumb } from '@/types';

defineProps<{
    context: App.Data.Schedule.JobFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('schedule'), url: '/jobs' },
    { label: t('schedule_add') },
];
</script>

<template>
    <Header :title="t('schedule_add')" :breadcrumbs="breadcrumbs" />
    <JobForm :context="context" />
</template>
