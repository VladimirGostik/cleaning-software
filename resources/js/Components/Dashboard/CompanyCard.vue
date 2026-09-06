<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowsRightLeftIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
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
    <article
        class="card border border-base-300 border-t-4 bg-base-100 shadow-sm"
        :class="{ 'border-t-primary': company.color === null }"
        :style="company.color ? { borderTopColor: company.color } : undefined"
        :aria-busy="isSwitching"
    >
        <div class="card-body pb-2">
            <h2 v-if="company.is_current" class="card-title items-center text-base">
                <TenantColorDot :color="company.color" />
                {{ company.name }}
                <span class="badge badge-primary badge-sm">{{ t('dashboard_current_company') }}</span>
            </h2>
            <button
                v-else
                type="button"
                class="card-title w-full items-center text-left text-base hover:text-primary"
                :aria-label="t('dashboard_switch_to', { name: company.name })"
                :disabled="switchingTo !== null"
                @click="navigate(company.tenant_id, '/')"
            >
                <TenantColorDot :color="company.color" />
                {{ company.name }}
                <ArrowsRightLeftIcon class="size-4 text-base-content/40" aria-hidden="true" />
                <span v-if="isSwitching" class="loading loading-spinner loading-xs" />
            </button>
            <span v-if="!company.supplier_complete" class="badge badge-warning badge-sm gap-1">
                <ExclamationTriangleIcon class="size-3" aria-hidden="true" />
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
        <p v-else class="card-body pt-0 text-sm text-base-content/60">{{ t('dashboard_company_no_access') }}</p>
    </article>
</template>
