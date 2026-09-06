<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ObjectStatusBadge from '@/Components/Objects/ObjectStatusBadge.vue';
import ContractStatusBadge from '@/Components/Contracts/ContractStatusBadge.vue';
import TaskFrequencyBadge from './TaskFrequencyBadge.vue';

const props = defineProps<{
    breakdown: App.Data.Schedule.WorkBreakdownDetailData;
    highlightTaskId?: string | null;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-200/40">
        <div class="card-body space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-sm">{{ props.breakdown.name }}</span>
                <ObjectStatusBadge :is-active="props.breakdown.is_active" />
                <a
                    v-if="props.breakdown.contract_id"
                    :href="`/contracts/${props.breakdown.contract_id}`"
                    class="link link-hover text-sm"
                >
                    {{ props.breakdown.contract_title }}
                </a>
                <ContractStatusBadge v-if="props.breakdown.contract_status" :status="props.breakdown.contract_status" />
                <a
                    v-if="props.breakdown.source_quote_id"
                    :href="`/quotes/${props.breakdown.source_quote_id}`"
                    class="link link-hover text-xs"
                >
                    {{ t('work_breakdown_source_quote') }}
                </a>
            </div>

            <p v-if="props.breakdown.tasks.length === 0" class="text-sm text-base-content/60">
                {{ t('work_breakdown_no_tasks') }}
            </p>

            <table v-else class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ t('work_breakdown_col_task') }}</th>
                        <th>{{ t('work_breakdown_col_frequency') }}</th>
                        <th class="hidden sm:table-cell">{{ t('work_breakdown_col_description') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="task in props.breakdown.tasks"
                        :key="task.id"
                        :class="{ 'bg-primary/10': task.id === props.highlightTaskId }"
                        :aria-current="task.id === props.highlightTaskId ? 'true' : undefined"
                    >
                        <td>{{ task.name }}</td>
                        <td><TaskFrequencyBadge :frequency="task.frequency" /></td>
                        <td class="hidden sm:table-cell">{{ task.description ?? t('empty_dash') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
