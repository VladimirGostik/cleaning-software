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
        class="rounded-box p-2 text-left hover:bg-base-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
        :disabled="switchingTo !== null"
        @click="navigate(tenantId, stat.href)"
    >
        <span class="block text-xs text-base-content/60">{{ stat.label }}</span>
        <span class="block text-lg font-semibold" :class="toneClass">{{ stat.value }}</span>
        <span v-if="stat.caption" class="block text-xs text-base-content/50">{{ stat.caption }}</span>
        <span v-for="row in stat.secondary" :key="row" class="block text-xs text-base-content/60">{{ row }}</span>
    </button>
</template>
