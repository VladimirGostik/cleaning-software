<script setup lang="ts">
    import { computed, reactive } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import {
        PencilSquareIcon,
        XCircleIcon,
        UserIcon,
        BuildingOfficeIcon,
        CalendarIcon,
        ClockIcon,
        DocumentTextIcon,
    } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import Can from '@/Components/Can.vue';
    import ConfirmDialog from '@/Components/ConfirmDialog.vue';
    import JobStatusBadge from '@/Components/Schedule/JobStatusBadge.vue';
    import JobTypeBadge from '@/Components/Schedule/JobTypeBadge.vue';
    import JobAssignPanel from '@/Components/Schedule/JobAssignPanel.vue';
    import WorkBreakdownView from '@/Components/Schedule/WorkBreakdownView.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useLocalizedDate } from '@/Composables/useLocalizedDate';

    interface Props {
        job: App.Data.Schedule.JobDetailData;
        membershipOptions: App.Data.Contracts.MembershipOptionData[];
        workBreakdown: App.Data.Schedule.WorkBreakdownDetailData | null;
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const { formatDate } = useLocalizedDate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const jobCan = computed(() => props.job.can ?? {});

    const ui = reactive({
        cancelConfirmOpen: false,
        processing: false,
    });

    function formatTime(time: string | null): string {
        if (!time) return t('common.empty_dash');
        return time.slice(0, 5);
    }

    function cancelJob(): void {
        ui.processing = true;
        router.post(
            `/jobs/${props.job.id}/cancel`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    ui.processing = false;
                    ui.cancelConfirmOpen = false;
                },
            },
        );
    }
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/jobs">{{ t('schedule.jobs_title') }}</Link>
                </li>
                <li>{{ job.object_name }}</li>
            </ul>
        </div>

        <PageHeader :title="job.object_name" :subtitle="job.client_name">
            <template #badges>
                <JobStatusBadge :status="job.status" size="md" />
                <JobTypeBadge :type="job.type" size="md" />
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6">
            <!-- LEFT: detail + breakdown -->
            <div class="space-y-6">
                <!-- Job detail card -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body gap-4">
                        <h2 class="card-title text-sm">{{ t('schedule.section.details') }}</h2>

                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div class="flex items-start gap-2">
                                <CalendarIcon class="w-4 h-4 text-base-content/40 mt-0.5 shrink-0" />
                                <div>
                                    <dt class="text-base-content/50 text-xs">
                                        {{ t('schedule.detail.scheduled_date') }}
                                    </dt>
                                    <dd class="font-medium font-mono">
                                        {{ formatDate(job.scheduled_date) }}
                                    </dd>
                                </div>
                            </div>

                            <div class="flex items-start gap-2">
                                <ClockIcon class="w-4 h-4 text-base-content/40 mt-0.5 shrink-0" />
                                <div>
                                    <dt class="text-base-content/50 text-xs">
                                        {{ t('schedule.detail.time') }}
                                    </dt>
                                    <dd class="font-medium font-mono">
                                        <template v-if="job.start_time">
                                            {{ formatTime(job.start_time) }}
                                            <span v-if="job.end_time"> – {{ formatTime(job.end_time) }}</span>
                                        </template>
                                        <template v-else>{{ t('common.empty_dash') }}</template>
                                    </dd>
                                </div>
                            </div>

                            <div class="flex items-start gap-2">
                                <UserIcon class="w-4 h-4 text-base-content/40 mt-0.5 shrink-0" />
                                <div>
                                    <dt class="text-base-content/50 text-xs">
                                        {{ t('schedule.detail.assignee') }}
                                    </dt>
                                    <dd class="font-medium">
                                        {{ job.assignee_display_name ?? t('job_status.unassigned') }}
                                    </dd>
                                </div>
                            </div>

                            <div v-if="job.contract_id" class="flex items-start gap-2">
                                <DocumentTextIcon class="w-4 h-4 text-base-content/40 mt-0.5 shrink-0" />
                                <div>
                                    <dt class="text-base-content/50 text-xs">
                                        {{ t('schedule.detail.contract') }}
                                    </dt>
                                    <dd>
                                        <Link
                                            :href="`/contracts/${job.contract_id}`"
                                            class="link link-hover text-sm"
                                        >
                                            {{ t('schedule.detail.contract') }}
                                        </Link>
                                    </dd>
                                </div>
                            </div>

                            <div v-if="job.note" class="sm:col-span-2">
                                <dt class="text-base-content/50 text-xs mb-0.5">
                                    {{ t('schedule.detail.note') }}
                                </dt>
                                <dd class="whitespace-pre-wrap text-sm">{{ job.note }}</dd>
                            </div>

                            <div v-if="job.is_invoiced" class="sm:col-span-2">
                                <span class="badge badge-success badge-sm">
                                    {{ t('schedule.detail.invoiced') }}
                                </span>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Work breakdown (read-only) -->
                <div v-if="workBreakdown">
                    <h2 class="text-sm font-semibold text-base-content/60 uppercase tracking-wide mb-2">
                        {{ t('schedule.section.breakdown') }}
                    </h2>
                    <WorkBreakdownView :breakdown="workBreakdown" />
                </div>
            </div>

            <!-- RIGHT sidebar -->
            <div class="space-y-4">
                <!-- Actions card -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body gap-2">
                        <h2 class="card-title text-sm">{{ t('schedule.section.actions') }}</h2>

                        <!-- Edit -->
                        <Can permission="edit schedule">
                            <Link
                                v-if="job.is_editable && jobCan.update"
                                :href="`/jobs/${job.id}/edit`"
                                class="btn btn-primary btn-sm w-full justify-start"
                            >
                                <PencilSquareIcon class="w-4 h-4" />
                                {{ t('schedule.action.edit') }}
                            </Link>
                        </Can>

                        <!-- Cancel -->
                        <Can permission="edit schedule">
                            <button
                                v-if="job.can_be_cancelled && jobCan.cancel"
                                type="button"
                                class="btn btn-error btn-sm w-full justify-start"
                                :disabled="ui.processing"
                                @click="ui.cancelConfirmOpen = true"
                            >
                                <XCircleIcon class="w-4 h-4" />
                                {{ t('schedule.action.cancel') }}
                            </button>
                        </Can>
                    </div>
                </div>

                <!-- Assign panel -->
                <Can permission="assign cleaners">
                    <JobAssignPanel
                        v-if="jobCan.assign"
                        :job-id="job.id"
                        :current-membership-id="job.assigned_membership_id"
                        :membership-options="membershipOptions"
                    />
                </Can>

                <!-- Links card -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body gap-2">
                        <h2 class="card-title text-sm">{{ t('schedule.section.links') }}</h2>

                        <div class="flex items-center gap-2 text-sm">
                            <UserIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                            <Link
                                v-if="job.client_id"
                                :href="`/clients/${job.client_id}`"
                                class="link link-hover"
                            >
                                {{ job.client_name }}
                            </Link>
                            <span v-else>{{ job.client_name }}</span>
                        </div>

                        <div class="flex items-center gap-2 text-sm">
                            <BuildingOfficeIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                            <Link :href="`/objects/${job.cleaning_object_id}`" class="link link-hover">
                                {{ job.object_name }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel confirm -->
        <ConfirmDialog
            :open="ui.cancelConfirmOpen"
            :title="t('schedule.action.cancel')"
            :body="t('schedule.cancel_confirm')"
            :confirm-label="t('schedule.action.cancel')"
            :cancel-label="t('cancel')"
            confirm-variant="error"
            @confirm="cancelJob"
            @cancel="ui.cancelConfirmOpen = false"
        />
    </div>
</template>
