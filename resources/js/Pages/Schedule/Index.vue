<script setup lang="ts">
    import { computed } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import { PlusIcon, ListBulletIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import Can from '@/Components/Can.vue';
    import JobFiltersBar from '@/Components/Schedule/JobFiltersBar.vue';
    import JobList from '@/Components/Schedule/JobList.vue';
    import JobCalendar from '@/Components/Schedule/JobCalendar.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useJobFilters } from '@/Composables/useJobFilters';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
    import type { PaginatedData } from '@/types/pagination.d';

    interface Props {
        jobs: PaginatedData<App.Data.Schedule.JobListItemData>;
        filters: App.Data.Schedule.JobIndexFilterData;
        statusOptions: SelectOption[];
        typeOptions: SelectOption[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const { state: filterState, ui, apply } = useJobFilters(props.filters);

    const subtitle = computed(() => {
        const base = t('schedule.subtitle').replace('{count}', String(props.jobs.meta.total));
        return pageProps.can.viewAllSchedule === false ? `${base} — ${t('schedule.own_only_hint')}` : base;
    });

    function goToDetail(id: string): void {
        router.visit(`/jobs/${id}`);
    }

    function onDatesSet(dateFrom: string, dateTo: string): void {
        filterState.date_from = dateFrom;
        filterState.date_to = dateTo;
        apply();
    }
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <PageHeader :title="t('schedule.jobs_title')" :subtitle="subtitle">
            <template #actions>
                <!-- View toggle -->
                <div class="join" role="group" :aria-label="t('schedule.view_mode_label')">
                    <button
                        type="button"
                        :class="[
                            'btn btn-sm join-item',
                            ui.viewMode === 'list' ? 'btn-primary' : 'btn-ghost',
                        ]"
                        :aria-pressed="ui.viewMode === 'list'"
                        @click="ui.viewMode = 'list'"
                    >
                        <ListBulletIcon class="w-4 h-4" />
                        {{ t('schedule.view_list') }}
                    </button>
                    <button
                        type="button"
                        :class="[
                            'btn btn-sm join-item',
                            ui.viewMode === 'calendar' ? 'btn-primary' : 'btn-ghost',
                        ]"
                        :aria-pressed="ui.viewMode === 'calendar'"
                        @click="ui.viewMode = 'calendar'"
                    >
                        <CalendarDaysIcon class="w-4 h-4" />
                        {{ t('schedule.view_calendar') }}
                    </button>
                </div>

                <!-- Calendar sub-view toggle (only in calendar mode) -->
                <div
                    v-if="ui.viewMode === 'calendar'"
                    class="join"
                    role="group"
                    :aria-label="t('schedule.calendar.month')"
                >
                    <button
                        type="button"
                        :class="[
                            'btn btn-sm join-item',
                            ui.calendarView === 'dayGridMonth' ? 'btn-secondary' : 'btn-ghost',
                        ]"
                        :aria-pressed="ui.calendarView === 'dayGridMonth'"
                        @click="ui.calendarView = 'dayGridMonth'"
                    >
                        {{ t('schedule.calendar.month') }}
                    </button>
                    <button
                        type="button"
                        :class="[
                            'btn btn-sm join-item',
                            ui.calendarView === 'timeGridWeek' ? 'btn-secondary' : 'btn-ghost',
                        ]"
                        :aria-pressed="ui.calendarView === 'timeGridWeek'"
                        @click="ui.calendarView = 'timeGridWeek'"
                    >
                        {{ t('schedule.calendar.week') }}
                    </button>
                </div>

                <Can permission="create schedule">
                    <Link href="/jobs/create" class="btn btn-primary btn-sm">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('schedule.add') }}
                    </Link>
                </Can>
            </template>
        </PageHeader>

        <JobFiltersBar
            v-model:search="filterState.search"
            v-model:status="filterState.status"
            v-model:type="filterState.type"
            v-model:date-from="filterState.date_from"
            v-model:date-to="filterState.date_to"
            :status-options="statusOptions"
            :type-options="typeOptions"
        />

        <JobList v-if="ui.viewMode === 'list'" :jobs="jobs" @select="goToDetail" />

        <JobCalendar v-else :jobs="jobs.data" :view="ui.calendarView" @dates-set="onDatesSet" />
    </div>
</template>
