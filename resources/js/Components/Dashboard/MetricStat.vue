<!-- ADR-F1: plain <button> (not <Link>) — a click here may trigger a tenant switch (POST)
     before navigating, so current-tenant and other-tenant targets share one template. -->
<script setup lang="ts">
import { computed } from 'vue';
import { useDashboardNavigation } from '@/Components/Dashboard/dashboardNavigation';
import type { MetricStatVm } from '@/Composables/useCompanyMetricGroups';

const props = defineProps<{ stat: MetricStatVm; tenantId: string }>();

const { navigate, switchingTo } = useDashboardNavigation();

const toneClass = computed<string>(
    () => ({ primary: 'text-primary', error: 'text-error', neutral: '' })[props.stat.tone],
);
</script>

<template>
    <button
        type="button"
        class="row-span-4 grid min-w-0 grid-rows-subgrid content-start rounded-box px-1.5 py-2 text-left hover:bg-base-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
        :disabled="switchingTo !== null"
        @click="navigate(tenantId, stat.href)"
    >
        <span class="text-xs leading-snug text-muted">{{ stat.label }}</span>
        <span
            class="mt-1 text-[length:clamp(0.8125rem,5cqi,1.125rem)] leading-tight font-medium whitespace-nowrap tabular-nums"
            :class="toneClass"
            >{{ stat.value }}</span
        >
        <span class="text-xs text-muted">{{ stat.caption }}</span>
        <span class="text-xs text-muted">
            <span v-for="row in stat.secondary" :key="row" class="block">{{ row }}</span>
        </span>
    </button>
</template>
