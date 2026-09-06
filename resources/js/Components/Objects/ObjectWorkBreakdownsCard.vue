<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import WorkBreakdownView from '@/Components/Schedule/WorkBreakdownView.vue';

const props = defineProps<{
    breakdowns: readonly App.Data.Schedule.WorkBreakdownDetailData[];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">
                {{ t('work_breakdown_title') }}
                <span v-if="props.breakdowns.length > 0" class="badge badge-ghost badge-sm">
                    {{ props.breakdowns.length }}
                </span>
            </h2>

            <p v-if="props.breakdowns.length === 0" class="text-base-content/60">
                {{ t('work_breakdown_empty') }}
            </p>

            <div v-else class="space-y-4">
                <WorkBreakdownView v-for="b in props.breakdowns" :key="b.id" :breakdown="b" />
            </div>
        </div>
    </div>
</template>
