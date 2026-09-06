<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import JobStatusBadge from '@/Components/Schedule/JobStatusBadge.vue';
import JobTypeBadge from '@/Components/Schedule/JobTypeBadge.vue';
import JobDetailCard from '@/Components/Schedule/JobDetailCard.vue';
import JobActionsCard from '@/Components/Schedule/JobActionsCard.vue';
import JobLinksCard from '@/Components/Schedule/JobLinksCard.vue';
import JobAssignPanel from '@/Components/Schedule/JobAssignPanel.vue';
import WorkBreakdownView from '@/Components/Schedule/WorkBreakdownView.vue';

import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    job: App.Data.Schedule.JobDetailData;
    membershipOptions: App.Data.Contracts.MembershipOptionData[];
    workBreakdown: App.Data.Schedule.WorkBreakdownDetailData | null;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('schedule'), url: '/jobs' },
    { label: props.job.object_name },
]);

const cancelConfirm = useDeleteConfirm<App.Data.Schedule.JobDetailData>({
    method: 'post',
    resolveUrl: (j) => `/jobs/${j.id}/cancel`,
    getTitle: () => t('schedule_action_cancel'),
    getDescription: () => t('schedule_cancel_confirm'),
});
</script>

<template>
    <AppLayout>
        <Header :title="job.object_name" :breadcrumbs="breadcrumbs">
            <template #actions>
                <JobStatusBadge :status="job.status" />
                <JobTypeBadge :type="job.type" />
            </template>
        </Header>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
            <div class="space-y-6">
                <JobDetailCard :job="job" />

                <div v-if="workBreakdown" class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('schedule_section_breakdown') }}</h2>
                        <WorkBreakdownView :breakdown="workBreakdown" :highlight-task-id="job.work_breakdown_task_id" />
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <JobActionsCard :job="job" @cancel="cancelConfirm.openModal(job)" />
                <JobAssignPanel
                    v-if="job.can.assign"
                    :job-id="job.id"
                    :current-membership-id="job.assigned_membership_id"
                    :membership-options="membershipOptions"
                />
                <JobLinksCard :job="job" />
            </div>
        </div>

        <ConfirmDeleteModal
            :is-open="cancelConfirm.state.isOpen"
            :title="cancelConfirm.getModalTitle()"
            :description="cancelConfirm.getModalDescription()"
            confirm-variant="warning"
            :confirm-label="t('schedule_action_cancel')"
            @cancel="cancelConfirm.closeModal"
            @confirm="cancelConfirm.confirmDelete"
        />
    </AppLayout>
</template>
