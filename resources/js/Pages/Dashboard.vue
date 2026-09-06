<script setup lang="ts">
import { computed, provide } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowPathIcon, BuildingOffice2Icon } from '@heroicons/vue/24/outline';
import Header from '@/Layouts/Header.vue';
import EmptyState from '@/Components/EmptyState.vue';
import CompanyCard from '@/Components/Dashboard/CompanyCard.vue';
import AlertsPanel from '@/Components/Dashboard/AlertsPanel.vue';
import { DashboardNavigationKey } from '@/Components/Dashboard/dashboardNavigation';
import { useTenantSwitchVisit } from '@/Composables/useTenantSwitchVisit';
import { formatShortDatetime } from '@/utils/date';

const props = defineProps<{ overview: App.Data.Dashboard.DashboardData }>();

const { t } = useI18n();

const navigation = useTenantSwitchVisit();
provide(DashboardNavigationKey, navigation);

const hasCompanies = computed<boolean>(() => props.overview.companies.length > 0);
const generatedLabel = computed<string>(() =>
    t('dashboard_generated_at', { time: formatShortDatetime(props.overview.generated_at) }),
);

function refresh(): void {
    router.reload({ only: ['overview'] });
}
</script>

<template>
    <Header :title="t('dashboard')">
        <template #actions>
            <span class="text-xs text-base-content/50">{{ generatedLabel }}</span>
            <button type="button" class="btn btn-ghost btn-sm" :aria-label="t('dashboard_refresh')" @click="refresh">
                <ArrowPathIcon class="size-4" />
            </button>
        </template>
    </Header>

    <EmptyState
        v-if="!hasCompanies"
        :title="t('dashboard_no_companies_title')"
        :description="t('dashboard_no_companies_description')"
        :icon="BuildingOffice2Icon"
    />

    <div v-else class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
            <CompanyCard v-for="company in overview.companies" :key="company.tenant_id" :company="company" />
        </div>
        <AlertsPanel
            class="xl:sticky xl:top-6 xl:self-start"
            :alerts="overview.alerts"
            :counts="overview.alert_counts"
        />
    </div>
</template>
