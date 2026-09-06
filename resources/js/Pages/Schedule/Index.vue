<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { CalendarDaysIcon, ListBulletIcon } from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { TableFilters } from '@/Components/DataTable';
import JobList from '@/Components/Schedule/JobList.vue';
import JobCalendar from '@/Components/Schedule/JobCalendar.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useJobCalendar } from '@/Composables/useJobCalendar';
import { readSpatieQuery, visitSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { JOB_STATUSES, JOB_TYPES, jobStatusKey, jobTypeKey } from '@/utils/enums';
import type { Breadcrumb, Paginator } from '@/types';
import type { FilterConfig } from '@/types/table';

const props = defineProps<{
    jobs: Paginator<App.Data.Schedule.JobListItemData>;
    filters?: Record<string, unknown>;
    filterOptions: {
        objects: App.Data.Objects.ObjectOptionData[];
        memberships: App.Data.Contracts.MembershipOptionData[];
    };
}>();

const { t } = useI18n();
const { allows } = useAuthorization();
const calendar = useJobCalendar();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('schedule') }];

const ui = reactive({ viewMode: 'list' as 'list' | 'calendar' });

const query = ref(readSpatieQuery());
watch(
    () => props.jobs,
    () => {
        query.value = readSpatieQuery();
    },
);

const hasActiveFilters = computed(() => Object.keys(query.value.filters).length > 0);
const showEmptyState = computed(() => ui.viewMode === 'list' && props.jobs.total === 0 && !hasActiveFilters.value);

const filterDefinitions = computed<FilterConfig[]>(() => {
    const definitions: FilterConfig[] = [
        { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
        {
            property: 'status',
            label: t('status'),
            type: 'select',
            placeholder: t('select_status'),
            defaultOperator: '=',
            options: JOB_STATUSES.map((v) => ({ value: v, label: t(jobStatusKey(v)) })),
        },
        {
            property: 'type',
            label: t('type'),
            type: 'select',
            placeholder: t('select_type'),
            defaultOperator: '=',
            options: JOB_TYPES.map((v) => ({ value: v, label: t(jobTypeKey(v)) })),
        },
    ];

    if (props.filterOptions.objects.length > 0) {
        definitions.push({
            property: 'cleaning_object_id',
            label: t('object'),
            type: 'select',
            placeholder: t('schedule_select_object'),
            defaultOperator: '=',
            options: props.filterOptions.objects.map((o) => ({
                value: o.id,
                label: o.client_name ? `${o.name} — ${o.client_name}` : o.name,
            })),
        });
    }

    if (props.filterOptions.memberships.length > 0) {
        definitions.push({
            property: 'assigned_membership_id',
            label: t('schedule_col_assignee'),
            type: 'select',
            placeholder: t('schedule_select_assignee'),
            defaultOperator: '=',
            options: props.filterOptions.memberships.map((m) => ({ value: m.id, label: m.label })),
        });
    }

    definitions.push({
        property: 'scheduled_date',
        label: t('date'),
        type: 'date',
        placeholder: t('date'),
        defaultOperator: '>=',
        operators: ['>=', '<=', 'between'],
    });

    return definitions;
});

function changeFilter(property: string, value: string | null): void {
    visitSpatieQuery({ [`filter[${property}]`]: value }, { only: ['jobs', 'filters'] });
}

function clearFilters(): void {
    const changes: Record<string, null> = { 'filter[search]': null };
    Object.keys(query.value.filters).forEach((key) => {
        const filter = filterDefinitions.value.find((f) => f.property === key);
        if (key === 'search' || filter?.clearable !== false) changes[`filter[${key}]`] = null;
    });
    visitSpatieQuery(changes, { only: ['jobs', 'filters'] });
}

watch(
    () => props.filters,
    () => {
        if (ui.viewMode === 'calendar') void calendar.reload();
    },
);
</script>

<template>
    <AppLayout>
        <Header :title="t('schedule')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <div class="join" role="group" :aria-label="t('schedule_view_mode')">
                    <button
                        type="button"
                        class="btn btn-sm join-item"
                        :class="{ 'btn-active': ui.viewMode === 'list' }"
                        :aria-pressed="ui.viewMode === 'list'"
                        @click="ui.viewMode = 'list'"
                    >
                        <ListBulletIcon class="size-4" />
                        {{ t('schedule_view_list') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm join-item"
                        :class="{ 'btn-active': ui.viewMode === 'calendar' }"
                        :aria-pressed="ui.viewMode === 'calendar'"
                        @click="ui.viewMode = 'calendar'"
                    >
                        <CalendarDaysIcon class="size-4" />
                        {{ t('schedule_view_calendar') }}
                    </button>
                </div>

                <a v-if="allows('create schedule')" href="/jobs/create" class="btn btn-primary btn-sm">
                    {{ t('schedule_add') }}
                </a>
            </template>
        </Header>

        <div v-if="!allows('view all schedule')" class="alert alert-info mb-4">
            <span>{{ t('schedule_own_only_hint') }}</span>
        </div>

        <TableFilters
            :filters="filterDefinitions"
            :query-filters="query.filters"
            :disable-search="ui.viewMode === 'calendar'"
            class="mb-4"
            @change="changeFilter"
            @clear="clearFilters"
        />

        <EmptyState
            v-if="showEmptyState"
            :title="t('schedule_empty')"
            :description="allows('view all schedule') ? t('schedule_empty_hint') : t('schedule_empty_own_hint')"
            :icon="CalendarDaysIcon"
        >
            <template v-if="allows('create schedule')" #cta>
                <a href="/jobs/create" class="btn btn-primary btn-sm">{{ t('schedule_add') }}</a>
            </template>
        </EmptyState>

        <template v-else>
            <div v-if="ui.viewMode === 'list'" class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <JobList :jobs="props.jobs" />
                </div>
            </div>

            <div v-else>
                <p class="mb-2 text-xs text-base-content/60">{{ t('schedule_calendar_filters_hint') }}</p>
                <JobCalendar
                    :events="calendar.state.events"
                    :loading="calendar.state.loading"
                    :error="calendar.state.error"
                    @dates-set="calendar.load"
                    @retry="calendar.reload"
                />
            </div>
        </template>
    </AppLayout>
</template>
