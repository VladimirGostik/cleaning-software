<script setup lang="ts">
import { computed } from 'vue';
import InformationCircleIcon from '~icons/material-symbols/info-outline-rounded';
import MetricStat from '@/Components/Dashboard/MetricStat.vue';
import type { MetricGroupVm } from '@/Composables/useCompanyMetricGroups';

const props = defineProps<{ group: MetricGroupVm; tenantId: string }>();

const headingId = computed<string>(() => `${props.tenantId}-${props.group.key}`);
</script>

<template>
    <section class="@container" :aria-labelledby="headingId">
        <div class="mb-1 flex items-center gap-1.5">
            <component :is="group.icon" class="text-muted" aria-hidden="true" />
            <h3 :id="headingId" class="text-sm font-medium">{{ group.title }}</h3>
        </div>
        <p v-if="group.hint" class="mb-1 flex items-center gap-1 text-xs text-muted">
            <InformationCircleIcon class="shrink-0" aria-hidden="true" />
            {{ group.hint }}
        </p>
        <div class="-mx-1.5 grid grid-cols-3 gap-x-1">
            <MetricStat v-for="stat in group.stats" :key="stat.key" :stat="stat" :tenant-id="tenantId" />
        </div>
    </section>
</template>
