<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import JobTypeBadge from './JobTypeBadge.vue';
import { formatDate, formatDatetime } from '@/utils/date';

const props = defineProps<{
    job: App.Data.Schedule.JobDetailData;
}>();

const { t } = useI18n();

function slice5(value: string | null): string {
    return value ? value.slice(0, 5) : '';
}
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('schedule_section_details') }}</h2>

            <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div>
                    <p class="text-xs text-base-content/50">{{ t('date') }}</p>
                    <p class="font-mono">{{ formatDate(props.job.scheduled_date) }}</p>
                </div>

                <div>
                    <p class="text-xs text-base-content/50">{{ t('schedule_col_time') }}</p>
                    <p v-if="props.job.start_time">
                        {{ slice5(props.job.start_time)
                        }}{{ props.job.end_time ? ` – ${slice5(props.job.end_time)}` : '' }}
                    </p>
                    <p v-else>{{ t('empty_dash') }}</p>
                </div>

                <div>
                    <p class="text-xs text-base-content/50">{{ t('schedule_col_assignee') }}</p>
                    <p>{{ props.job.assignee_display_name ?? t('job_status_unassigned') }}</p>
                </div>

                <div>
                    <p class="text-xs text-base-content/50">{{ t('type') }}</p>
                    <JobTypeBadge :type="props.job.type" />
                </div>

                <div v-if="props.job.task_name">
                    <p class="text-xs text-base-content/50">{{ t('schedule_detail_task') }}</p>
                    <p>{{ props.job.task_name }}</p>
                </div>

                <div v-if="props.job.completed_at">
                    <p class="text-xs text-base-content/50">{{ t('schedule_detail_completed_at') }}</p>
                    <p>{{ formatDatetime(props.job.completed_at) }}</p>
                </div>

                <div v-if="props.job.cancelled_at">
                    <p class="text-xs text-base-content/50">{{ t('schedule_detail_cancelled_at') }}</p>
                    <p class="text-error">{{ formatDatetime(props.job.cancelled_at) }}</p>
                </div>

                <div v-if="props.job.note" class="col-span-2 sm:col-span-4">
                    <p class="text-xs text-base-content/50">{{ t('note') }}</p>
                    <p class="whitespace-pre-wrap">{{ props.job.note }}</p>
                </div>

                <div v-if="props.job.is_invoiced">
                    <span class="badge badge-success badge-sm">{{ t('schedule_invoiced') }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
