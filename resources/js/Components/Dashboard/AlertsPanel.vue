<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { CheckCircleIcon } from '@heroicons/vue/24/outline';
import EmptyState from '@/Components/EmptyState.vue';
import AlertRow from '@/Components/Dashboard/AlertRow.vue';
import { ALERT_TYPE_STYLE } from '@/Components/Dashboard/alertTypeStyle';
import { dashboardAlertTypeKey } from '@/utils/enums';

const props = defineProps<{
    alerts: App.Data.Dashboard.DashboardAlertData[];
    counts: App.Data.Dashboard.DashboardAlertCountData[];
}>();

const { t } = useI18n();

interface AlertSection {
    type: App.Enums.DashboardAlertTypeEnum;
    label: string;
    count: number;
    rows: App.Data.Dashboard.DashboardAlertData[];
    hidden: number;
}

const sections = computed<AlertSection[]>(() =>
    props.counts
        .filter((entry) => entry.count > 0)
        .map((entry) => {
            const rows = props.alerts.filter((alert) => alert.type === entry.type);
            return {
                type: entry.type,
                label: t(dashboardAlertTypeKey(entry.type)),
                count: entry.count,
                rows,
                hidden: Math.max(0, entry.count - rows.length),
            };
        }),
);

const isEmpty = computed<boolean>(() => sections.value.length === 0);
</script>

<template>
    <aside class="card border border-base-300 bg-base-100 shadow-sm" aria-labelledby="dashboard-alerts-title">
        <div class="card-body gap-4">
            <h2 id="dashboard-alerts-title" class="card-title text-base">{{ t('dashboard_alerts_title') }}</h2>
            <EmptyState
                v-if="isEmpty"
                :title="t('dashboard_alerts_empty_title')"
                :description="t('dashboard_alerts_empty_description')"
                :icon="CheckCircleIcon"
            />
            <div v-else class="space-y-4">
                <section
                    v-for="section in sections"
                    :key="section.type"
                    :aria-labelledby="`dashboard-alerts-${section.type}`"
                >
                    <div class="mb-1 flex items-center gap-2">
                        <h3 :id="`dashboard-alerts-${section.type}`" class="text-sm font-medium">
                            {{ section.label }}
                        </h3>
                        <span class="badge badge-sm" :class="ALERT_TYPE_STYLE[section.type].badge">{{
                            section.count
                        }}</span>
                    </div>
                    <ul class="divide-y divide-base-200">
                        <AlertRow
                            v-for="alert in section.rows"
                            :key="`${alert.tenant_id}-${alert.type}-${alert.title}`"
                            :alert="alert"
                        />
                    </ul>
                    <p v-if="section.hidden > 0" class="mt-1 text-xs text-base-content/60">
                        {{ t('dashboard_alerts_more', { count: section.hidden }) }}
                    </p>
                </section>
            </div>
        </div>
    </aside>
</template>
