<!-- ADR-F1: plain <button> (not <Link>) — a click here may trigger a tenant switch (POST)
     before navigating, so current-tenant and other-tenant targets share one template. -->
<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDashboardNavigation } from '@/Components/Dashboard/dashboardNavigation';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { formatDate } from '@/utils/date';
import { dashboardAlertTypeKey } from '@/utils/enums';
import { ALERT_TYPE_STYLE } from '@/Components/Dashboard/alertTypeStyle';
import TenantColorDot from '@/Components/Tenants/TenantColorDot.vue';

const props = defineProps<{ alert: App.Data.Dashboard.DashboardAlertData }>();

const { t } = useI18n();
const { money } = useMoneyFormat();
const { navigate, switchingTo } = useDashboardNavigation();

const typeLabel = computed<string>(() => t(dashboardAlertTypeKey(props.alert.type)));
const dotClass = computed<string>(() => ALERT_TYPE_STYLE[props.alert.type].dot);
const amount = computed<string | null>(() =>
    props.alert.amount !== null && props.alert.currency !== null
        ? money(props.alert.amount, props.alert.currency)
        : null,
);
const daysLabel = computed<string | null>(() => {
    if (props.alert.days === null) return null;
    return props.alert.type === 'overdue_invoice'
        ? t('dashboard_days_overdue', { count: props.alert.days })
        : t('dashboard_days_left', { count: props.alert.days });
});
const dueLabel = computed<string | null>(() => (props.alert.due_date ? formatDate(props.alert.due_date) : null));
const isSwitching = computed<boolean>(() => switchingTo.value === props.alert.tenant_id);
</script>

<template>
    <li>
        <button
            type="button"
            class="flex w-full min-w-0 items-start gap-2 rounded-box px-2 py-2 text-left hover:bg-base-200"
            :disabled="switchingTo !== null"
            @click="navigate(alert.tenant_id, alert.url)"
        >
            <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="dotClass" aria-hidden="true" />
            <span class="sr-only">{{ typeLabel }}</span>
            <span class="min-w-0 flex-1">
                <span class="flex min-w-0 items-center gap-1.5">
                    <TenantColorDot :color="alert.tenant_color" />
                    <span class="truncate text-xs text-base-content/60">{{ alert.tenant_name }}</span>
                </span>
                <span class="block truncate text-sm font-medium">{{ alert.title }}</span>
                <span v-if="alert.subtitle" class="block truncate text-xs text-base-content/50">{{
                    alert.subtitle
                }}</span>
            </span>
            <span class="shrink-0 text-right">
                <span v-if="amount" class="block text-sm font-semibold">{{ amount }}</span>
                <span
                    v-if="daysLabel"
                    class="block text-xs"
                    :class="alert.type === 'overdue_invoice' ? 'text-error' : 'text-base-content/60'"
                >
                    {{ daysLabel }}
                </span>
                <time
                    v-if="dueLabel"
                    :datetime="alert.due_date ?? undefined"
                    class="block text-xs text-base-content/50"
                >
                    {{ dueLabel }}
                </time>
                <span v-if="isSwitching" class="loading loading-spinner loading-xs" />
            </span>
        </button>
    </li>
</template>
