<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AutorenewIcon from '~icons/material-symbols/autorenew-rounded';
import { quoteBillingPeriodKey } from '@/utils/enums';

const props = defineProps<{
    quoteId: string;
    periods: readonly App.Enums.QuoteBillingPeriodEnum[];
}>();

const { t } = useI18n();

function href(period: App.Enums.QuoteBillingPeriodEnum): string {
    return `/recurring-invoices/create?quote_id=${props.quoteId}&billing_period=${period}`;
}
</script>

<template>
    <Link
        v-if="props.periods.length === 1"
        :href="href(props.periods[0])"
        class="btn btn-sm btn-primary w-full justify-start"
    >
        <AutorenewIcon class="size-5" />
        {{ t('quote_action_create_recurring') }}
    </Link>

    <div v-else-if="props.periods.length > 1" class="dropdown w-full">
        <div tabindex="0" role="button" aria-haspopup="menu" class="btn btn-sm btn-primary w-full justify-start">
            <AutorenewIcon class="size-5" />
            {{ t('quote_action_create_recurring') }}
        </div>
        <ul
            tabindex="0"
            class="dropdown-content menu menu-sm bg-base-100 text-base-content rounded-box shadow-float z-50 w-full p-1"
        >
            <li v-for="period in props.periods" :key="period">
                <Link :href="href(period)">{{ t(quoteBillingPeriodKey(period)) }}</Link>
            </li>
        </ul>
    </div>
</template>
