<script setup lang="ts">
import { computed } from 'vue';
import { InformationCircleIcon } from '@heroicons/vue/24/outline';
import MetricStat from '@/Components/Dashboard/MetricStat.vue';
import type { MetricGroupVm } from '@/Composables/useCompanyMetricGroups';

const props = defineProps<{ group: MetricGroupVm; tenantId: string }>();

const headingId = computed<string>(() => `${props.tenantId}-${props.group.key}`);
</script>

<template>
    <section :aria-labelledby="headingId">
        <div class="mb-1 flex items-center gap-1.5">
            <component :is="group.icon" class="size-4 text-base-content/50" aria-hidden="true" />
            <h3 :id="headingId" class="text-sm font-medium">{{ group.title }}</h3>
        </div>
        <p v-if="group.hint" class="mb-1 flex items-center gap-1 text-xs text-base-content/60">
            <InformationCircleIcon class="size-3.5 shrink-0" aria-hidden="true" />
            {{ group.hint }}
        </p>
        <div class="grid grid-cols-3 gap-1">
            <MetricStat v-for="stat in group.stats" :key="stat.key" :stat="stat" :tenant-id="tenantId" />
        </div>
    </section>
</template>
