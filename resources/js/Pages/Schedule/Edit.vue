<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import JobForm from '@/Components/Schedule/JobForm.vue';

import type { Breadcrumb } from '@/types';

const props = defineProps<{
    job: App.Data.Schedule.JobDetailData;
    context: App.Data.Schedule.JobFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('schedule'), url: '/jobs' },
    { label: props.job.object_name, url: `/jobs/${props.job.id}` },
    { label: t('schedule_edit') },
]);
</script>

<template>
    <AppLayout>
        <Header :title="t('schedule_edit')" :breadcrumbs="breadcrumbs" />

        <div v-if="!job.is_editable" class="alert alert-warning">
            <span>{{ t('job_not_editable') }}</span>
            <a :href="`/jobs/${job.id}`" class="btn btn-sm">{{ t('view') }}</a>
        </div>

        <JobForm v-else :context="context" :job="job" />
    </AppLayout>
</template>
