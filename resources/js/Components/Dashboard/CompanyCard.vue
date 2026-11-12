<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowsRightLeftIcon from '~icons/material-symbols/swap-horiz-outline-rounded';
import TenantColorDot from '@/Components/Tenants/TenantColorDot.vue';
import CompanyMetricGroup from '@/Components/Dashboard/CompanyMetricGroup.vue';
import { useDashboardNavigation } from '@/Components/Dashboard/dashboardNavigation';
import { useCompanyMetricGroups } from '@/Composables/useCompanyMetricGroups';

const props = defineProps<{ company: App.Data.Dashboard.CompanyOverviewData }>();

const { t } = useI18n();
const { navigate, switchingTo } = useDashboardNavigation();

const groups = useCompanyMetricGroups(() => props.company);
const hasAnyGroup = computed<boolean>(() => groups.value.length > 0);
const isSwitching = computed<boolean>(() => switchingTo.value === props.company.tenant_id);
</script>

<template>
    <article class="card bg-base-100" :aria-busy="isSwitching">
        <div class="card-body pb-2">
            <h2 v-if="company.is_current" class="card-title items-center">
                <TenantColorDot :color="company.color" />
                {{ company.name }}
                <span class="kg-chip kg-chip--planned kg-chip--sm">{{ t('dashboard_current_company') }}</span>
            </h2>
            <button
                v-else
                type="button"
                class="card-title w-full items-center text-left hover:text-primary"
                :aria-label="t('dashboard_switch_to', { name: company.name })"
                :disabled="switchingTo !== null"
                @click="navigate(company.tenant_id, '/')"
            >
                <TenantColorDot :color="company.color" />
                {{ company.name }}
                <ArrowsRightLeftIcon class="text-muted" aria-hidden="true" />
                <span v-if="isSwitching" class="loading loading-spinner loading-xs" />
            </button>
            <span v-if="!company.supplier_complete" class="kg-chip kg-chip--warning kg-chip--sm">
                {{ t('dashboard_supplier_incomplete') }}
            </span>
        </div>
        <div v-if="hasAnyGroup" class="card-body gap-4 pt-0">
            <CompanyMetricGroup
                v-for="group in groups"
                :key="group.key"
                :group="group"
                :tenant-id="company.tenant_id"
            />
        </div>
        <p v-else class="card-body pt-0 text-sm text-muted">{{ t('dashboard_company_no_access') }}</p>
    </article>
</template>
