<script setup lang="ts">
    import { useTranslate } from '@/Composables/useTranslate';

    defineProps<{
        breakdown: App.Data.Schedule.WorkBreakdownDetailData;
    }>();

    const { t } = useTranslate();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-sm">{{ breakdown.name }}</h3>
                <span :class="['badge badge-sm', breakdown.is_active ? 'badge-success' : 'badge-ghost']">
                    {{ breakdown.is_active ? t('work_breakdown.active') : t('work_breakdown.inactive') }}
                </span>
            </div>

            <div v-if="breakdown.tasks.length === 0" class="text-sm text-base-content/50">
                {{ t('work_breakdown.no_tasks') }}
            </div>

            <div v-else class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead>
                        <tr>
                            <th>{{ t('work_breakdown.col.task') }}</th>
                            <th>{{ t('work_breakdown.col.frequency') }}</th>
                            <th class="hidden sm:table-cell">{{ t('work_breakdown.col.description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in breakdown.tasks" :key="task.id">
                            <td class="font-medium">{{ task.name }}</td>
                            <td>
                                <span class="badge badge-sm badge-ghost">
                                    {{ t('task_frequency.' + task.frequency) }}
                                </span>
                            </td>
                            <td class="hidden sm:table-cell text-sm text-base-content/60">
                                {{ task.description ?? t('common.empty_dash') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
