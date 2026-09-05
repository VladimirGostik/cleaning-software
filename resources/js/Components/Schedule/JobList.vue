<script setup lang="ts">
    import { ChevronRightIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';
    import { Link } from '@inertiajs/vue3';
    import { useTranslate } from '@/Composables/useTranslate';
    import { useLocalizedDate } from '@/Composables/useLocalizedDate';
    import Pagination from '@/Components/Pagination.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Can from '@/Components/Can.vue';
    import JobStatusBadge from './JobStatusBadge.vue';
    import JobTypeBadge from './JobTypeBadge.vue';
    import type { PaginatedData } from '@/types/pagination.d';

    defineProps<{
        jobs: PaginatedData<App.Data.Schedule.JobListItemData>;
    }>();

    const emit = defineEmits<{
        (e: 'select', id: string): void;
    }>();

    const { t } = useTranslate();
    const { formatDate } = useLocalizedDate();

    function formatTime(time: string | null): string {
        if (!time) return '';
        return time.slice(0, 5);
    }
</script>

<template>
    <div>
        <!-- Desktop table -->
        <div class="card bg-base-100 shadow-sm hidden md:block">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>{{ t('schedule.col.date') }}</th>
                            <th>{{ t('schedule.col.time') }}</th>
                            <th>{{ t('schedule.col.object') }}</th>
                            <th>{{ t('schedule.col.client') }}</th>
                            <th>{{ t('schedule.col.assignee') }}</th>
                            <th>{{ t('schedule.col.type') }}</th>
                            <th>{{ t('schedule.col.status') }}</th>
                            <th class="w-8" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in jobs.data"
                            :key="row.id"
                            class="hover cursor-pointer"
                            role="button"
                            tabindex="0"
                            @click="emit('select', row.id)"
                            @keydown.enter="emit('select', row.id)"
                        >
                            <td class="font-mono text-sm">{{ formatDate(row.scheduled_date) }}</td>
                            <td class="text-sm text-base-content/70">
                                <span v-if="row.start_time">
                                    {{ formatTime(row.start_time) }}
                                    <span v-if="row.end_time">– {{ formatTime(row.end_time) }}</span>
                                </span>
                                <span v-else class="text-base-content/40">—</span>
                            </td>
                            <td class="font-medium">{{ row.object_name }}</td>
                            <td class="text-sm text-base-content/70">{{ row.client_name }}</td>
                            <td class="text-sm">
                                {{ row.assignee_display_name ?? t('common.empty_dash') }}
                            </td>
                            <td><JobTypeBadge :type="row.type" /></td>
                            <td><JobStatusBadge :status="row.status" /></td>
                            <td>
                                <ChevronRightIcon class="w-4 h-4 text-base-content/40" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="jobs.data.length > 0" class="card-body py-3">
                <Pagination :meta="jobs.meta" :links="jobs.links" />
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden space-y-3">
            <div
                v-for="row in jobs.data"
                :key="row.id"
                class="card bg-base-100 shadow-sm cursor-pointer"
                role="button"
                tabindex="0"
                @click="emit('select', row.id)"
                @keydown.enter="emit('select', row.id)"
            >
                <div class="card-body p-4">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <p class="font-medium">{{ row.object_name }}</p>
                            <p class="text-sm text-base-content/60 mt-0.5">{{ row.client_name }}</p>
                        </div>
                        <JobStatusBadge :status="row.status" />
                    </div>
                    <div class="flex items-center gap-3 mt-2 text-sm text-base-content/70">
                        <span class="font-mono">{{ formatDate(row.scheduled_date) }}</span>
                        <span v-if="row.start_time">{{ formatTime(row.start_time) }}</span>
                        <JobTypeBadge :type="row.type" />
                    </div>
                    <p v-if="row.assignee_display_name" class="text-sm text-base-content/60 mt-1">
                        {{ row.assignee_display_name }}
                    </p>
                </div>
            </div>

            <Pagination v-if="jobs.data.length > 0" :meta="jobs.meta" :links="jobs.links" />
        </div>

        <EmptyState
            v-if="jobs.data.length === 0"
            :title="t('schedule.empty')"
            :description="t('schedule.empty_hint')"
            :icon="CalendarDaysIcon"
        >
            <template #cta>
                <Can permission="create schedule">
                    <Link href="/jobs/create" class="btn btn-primary btn-sm">
                        {{ t('schedule.add') }}
                    </Link>
                </Can>
            </template>
        </EmptyState>
    </div>
</template>
