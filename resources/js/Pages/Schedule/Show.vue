<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
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

// All four status transitions share one confirm modal — the item carried through it is the
// action itself, not the job, so adding a transition means adding a row to CONFIRM_VARIANT.
type JobStatusAction = 'start' | 'complete' | 'unapprove' | 'cancel';

const CONFIRM_VARIANT: Record<JobStatusAction, 'primary' | 'success' | 'error' | 'warning'> = {
    start: 'primary',
    complete: 'success',
    unapprove: 'error',
    cancel: 'warning',
};

const statusConfirm = useDeleteConfirm<JobStatusAction>({
    method: 'post',
    resolveUrl: (action) => `/jobs/${props.job.id}/${action}`,
    getTitle: (action) => t(`schedule_action_${action}`),
    getDescription: (action) => t(`schedule_${action}_confirm`),
});

const confirmVariant = computed(() => (statusConfirm.state.item ? CONFIRM_VARIANT[statusConfirm.state.item] : 'error'));
</script>

<template>
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
            <JobActionsCard
                :job="job"
                @start="statusConfirm.openModal('start')"
                @complete="statusConfirm.openModal('complete')"
                @unapprove="statusConfirm.openModal('unapprove')"
                @cancel="statusConfirm.openModal('cancel')"
            />
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
        :is-open="statusConfirm.state.isOpen"
        :title="statusConfirm.getModalTitle()"
        :description="statusConfirm.getModalDescription()"
        :confirm-variant="confirmVariant"
        :confirm-label="statusConfirm.getModalTitle()"
        @cancel="statusConfirm.closeModal"
        @confirm="statusConfirm.confirmDelete"
    />
</template>
