<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AutorenewIcon from '~icons/material-symbols/autorenew-outline-rounded';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import { quoteBillingPeriodKey } from '@/utils/enums';

const props = defineProps<{
    quoteId: string;
    periods: readonly App.Enums.QuoteBillingPeriodEnum[];
}>();

const { t } = useI18n();

const open = ref(false);

function href(period: App.Enums.QuoteBillingPeriodEnum): string {
    return `/recurring-invoices/create?quote_id=${props.quoteId}&billing_period=${period}`;
}
</script>

<template>
    <Link v-if="props.periods.length === 1" :href="href(props.periods[0])" class="btn btn-primary w-full justify-start">
        <AutorenewIcon />
        {{ t('quote_action_create_recurring') }}
    </Link>

    <AnchoredPopover v-else-if="props.periods.length > 1" v-model:open="open" block class="w-56 p-1">
        <template #trigger="{ toggle, open: isOpen }">
            <button
                type="button"
                class="btn btn-primary w-full justify-start"
                aria-haspopup="menu"
                :aria-expanded="isOpen"
                @click="toggle"
            >
                <AutorenewIcon />
                {{ t('quote_action_create_recurring') }}
            </button>
        </template>
        <ul class="menu w-full p-0">
            <li v-for="period in props.periods" :key="period">
                <Link :href="href(period)">{{ t(quoteBillingPeriodKey(period)) }}</Link>
            </li>
        </ul>
    </AnchoredPopover>
</template>
